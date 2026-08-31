<?php

namespace Modules\Report\App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Evaluation\App\Services\EvaluationConfigVersionService;
use Modules\Evaluation\App\Services\EvaluationScoreComputeService;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;
use Modules\Report\App\Models\Report;
use Modules\Report\App\Repositories\Contracts\ReportRepositoryInterface;

/**
 * Cấu hình và đọc kết quả báo cáo.
 *
 * Báo cáo đánh giá nhân sự luôn tính lại từ dữ liệu gốc, nhưng dùng đúng phiên
 * bản khung chấm điểm đã chốt lúc tạo — nên mở lại báo cáo cũ vẫn ra đúng con
 * số cũ dù phòng ban đã sửa cấu hình.
 */
class ReportService
{
    /** Cột của báo cáo đánh giá nhân sự, theo thứ tự hiển thị cố định. */
    public const EVALUATION_COLUMNS = [
        'start_score' => 'Điểm khởi đầu',
        'task_adjustment' => 'Điểm công việc',
        'bonus' => 'Điểm cộng',
        'penalty' => 'Điểm trừ',
        'final_score' => 'Điểm cuối',
        'classification' => 'Xếp loại',
    ];

    public const DEFAULT_COLUMNS = [
        'start_score',
        'bonus',
        'penalty',
        'final_score',
        'classification',
    ];

    public function __construct(
        private readonly ReportRepositoryInterface $reports,
        private readonly EvaluationConfigVersionService $versions,
        private readonly EvaluationScoreComputeService $compute,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * Tạo báo cáo đánh giá nhân sự. Phòng ban chưa từng chốt phiên bản khung
     * chấm điểm thì chốt luôn phiên bản đầu tiên, tránh chặn người dùng giữa
     * chừng chỉ vì thiếu một thao tác thủ công.
     *
     * @param  array<string, mixed>  $data
     */
    public function createPersonnelEvaluation(int $departmentId, User $actor, array $data): Report
    {
        $version = $this->versions->activeOrPublish($departmentId, (int) $actor->id);

        return DB::transaction(function () use ($departmentId, $actor, $data, $version) {
            $report = $this->reports->create([
                'department_id' => $departmentId,
                'report_type' => Report::TYPE_PERSONNEL_EVALUATION,
                'title' => trim((string) $data['title']),
                'period_type' => $data['period_type'] ?? 'month',
                'period_from' => $data['period_from'],
                'period_to' => $data['period_to'],
                'evaluation_config_version_id' => $version->id,
                'status' => Report::STATUS_DRAFT,
                'created_by' => (int) $actor->id,
                'updated_by' => (int) $actor->id,
            ]);

            $this->syncRelations($report, $data);

            return $this->reports->find((int) $report->id) ?? $report;
        });
    }

    /**
     * Xem trước số liệu trước khi tạo báo cáo.
     *
     * Không ghi gì vào cơ sở dữ liệu và không chốt phiên bản mới — chỉ tính
     * thử trên phiên bản đang áp dụng để người tạo thấy con số thật trước khi
     * quyết định lưu. Phòng ban chưa chốt phiên bản nào thì chưa xem trước
     * được, nói rõ ra thay vì tự chốt sau lưng người dùng.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function previewPersonnelEvaluation(int $departmentId, array $data): array
    {
        $version = $this->versions->activeForDepartment($departmentId);

        if ($version === null) {
            throw ValidationException::withMessages([
                'report' => 'Phòng ban chưa có phiên bản khung chấm điểm nào. Số liệu sẽ được tính khi bạn tạo báo cáo.',
            ]);
        }

        $selected = $this->intList($data['filter_user_ids'] ?? []);
        $people = $this->users
            ->allActiveByDepartment($departmentId)
            ->when(
                $selected !== [],
                fn (Collection $rows) => $rows->filter(
                    fn ($user) => in_array((int) $user->id, $selected, true),
                ),
            )
            ->map(fn ($user) => ['id' => (int) $user->id, 'name' => (string) $user->name])
            ->values()
            ->all();

        $result = $this->compute->computeForPeople(
            $people,
            $version,
            (string) $data['period_from'],
            (string) $data['period_to'],
        );

        // Bảng xem trước chỉ cần vài dòng đầu — người tạo chỉ cần thấy số liệu
        // có ra hình hài hợp lý không, chưa cần xem hết cả phòng ban.
        $rows = array_map(
            static fn (array $row) => [
                'user_id' => $row['user_id'],
                'user_name' => $row['user_name'],
                'final_score' => $row['final_score'],
                'classification_label' => $row['classification_label'],
                'task_count' => $row['task_count'],
                'event_count' => $row['event_count'],
            ],
            $result['rows'],
        );

        usort($rows, static fn (array $a, array $b) => $b['final_score'] <=> $a['final_score']);

        return [
            'summary' => $result['summary'],
            'rows' => array_slice($rows, 0, 5),
            'version_no' => $version->version_no,
        ];
    }

    /**
     * Sửa cấu hình báo cáo. Kỳ báo cáo và phiên bản khung chấm điểm chỉ đổi
     * được khi còn nháp — đã lưu thì giữ nguyên để số liệu không đổi về sau.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Report $report, User $actor, array $data): Report
    {
        return DB::transaction(function () use ($report, $actor, $data) {
            $payload = ['updated_by' => (int) $actor->id];

            if (array_key_exists('title', $data)) {
                $payload['title'] = trim((string) $data['title']);
            }

            if ($report->status === Report::STATUS_DRAFT) {
                foreach (['period_type', 'period_from', 'period_to'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $payload[$field] = $data[$field];
                    }
                }
            }

            $updated = $this->reports->update($report, $payload);
            $this->syncRelations($updated, $data);

            return $this->reports->find((int) $updated->id) ?? $updated;
        });
    }

    /**
     * Chốt lưu — từ đây kỳ báo cáo và phiên bản không đổi được nữa.
     *
     * Chụp luôn danh sách nhân sự trong phạm vi: nếu vẫn lấy động theo phòng
     * ban thì người nghỉ việc sau kỳ sẽ biến mất khỏi báo cáo cũ, còn người
     * mới chuyển đến lại hiện ra trong kỳ họ chưa làm ở đó.
     */
    public function save(Report $report, User $actor): Report
    {
        return DB::transaction(function () use ($report, $actor) {
            $this->reports->syncPeopleSnapshot($report, $this->livePeopleFor($report));

            return $this->reports->update($report, [
                'status' => Report::STATUS_SAVED,
                'updated_by' => (int) $actor->id,
            ]);
        });
    }

    public function delete(Report $report): void
    {
        $this->reports->delete($report);
    }

    public function find(int $id): ?Report
    {
        return $this->reports->find($id);
    }

    /**
     * Danh sách báo cáo người dùng được thấy.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function listVisible(User $user, array $filters = []): array
    {
        $departmentId = (int) ($user->department_id ?? 0);

        if ($user->isSuperAdmin() || $user->allows('report.*')) {
            $reports = $this->reports->allAcrossDepartments($filters);
        } elseif ($departmentId > 0 && $user->allowsScoped('report.manage_department', 'department', $departmentId)) {
            $reports = $this->reports->allByDepartment($departmentId, $filters);
        } else {
            $reports = $this->reports->allSharedWithUser((int) $user->id, $filters);
        }

        return $reports
            ->map(fn (Report $report) => $this->presentSummary($report))
            ->values()
            ->all();
    }

    /**
     * Cùng phạm vi với listVisible nhưng phân trang ở máy chủ — trang danh
     * sách không tải toàn bộ bản ghi về rồi cắt trang bằng JavaScript nữa.
     *
     * @param  array<string, mixed>  $filters
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|null>}
     */
    public function paginateVisible(User $user, array $filters, int $perPage, int $page): array
    {
        $departmentId = (int) ($user->department_id ?? 0);

        if ($user->isSuperAdmin() || $user->allows('report.*')) {
            [$scope, $scopeId] = ['all', 0];
        } elseif ($departmentId > 0 && $user->allowsScoped('report.manage_department', 'department', $departmentId)) {
            [$scope, $scopeId] = ['department', $departmentId];
        } else {
            [$scope, $scopeId] = ['shared', (int) $user->id];
        }

        $paginator = $this->reports->paginateVisible($scope, $scopeId, $filters, $perPage, $page);

        return [
            'data' => collect($paginator->items())
                ->map(fn (Report $report) => $this->presentSummary($report))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    /**
     * Người dùng có được xem báo cáo này không — người quản lý phòng ban sở
     * hữu báo cáo, hoặc người có tên trong danh sách được chia sẻ.
     */
    public function canView(Report $report, User $user): bool
    {
        if ($user->isSuperAdmin() || $user->allows('report.*')) {
            return true;
        }

        if ($user->allowsScoped('report.manage_department', 'department', (int) $report->department_id)) {
            return true;
        }

        return $report->viewers->contains(
            fn ($viewer) => (int) $viewer->user_id === (int) $user->id,
        );
    }

    public function canManage(Report $report, User $user): bool
    {
        if ($user->isSuperAdmin() || $user->allows('report.*')) {
            return true;
        }

        return $user->allowsScoped('report.manage_department', 'department', (int) $report->department_id);
    }

    /**
     * Kết quả đầy đủ của báo cáo: tổng hợp và bảng theo từng nhân sự.
     *
     * @return array<string, mixed>
     */
    public function present(Report $report): array
    {
        $version = $report->evaluationConfigVersion;

        if ($version === null) {
            throw ValidationException::withMessages([
                'report' => 'Báo cáo này thiếu phiên bản khung chấm điểm nên không tính được điểm.',
            ]);
        }

        $people = $this->peopleFor($report);
        $result = $this->compute->computeForPeople(
            $people,
            $version,
            $report->period_from->toDateString(),
            $report->period_to->toDateString(),
        );

        return [
            'report' => $this->presentDetail($report),
            'summary' => $result['summary'],
            'rows' => array_map(
                fn (array $row) => $this->applyColumns($row, $report),
                $result['rows'],
            ),
        ];
    }

    /**
     * Chi tiết điểm của một nhân sự trong báo cáo — kèm đóng góp của từng công
     * việc và từng lần ghi nhận hành vi.
     *
     * @return array<string, mixed>
     */
    public function presentEmployeeDetail(Report $report, int $userId): array
    {
        $version = $report->evaluationConfigVersion;

        if ($version === null) {
            throw ValidationException::withMessages([
                'report' => 'Báo cáo này thiếu phiên bản khung chấm điểm nên không tính được điểm.',
            ]);
        }

        $people = $this->peopleFor($report);
        $person = collect($people)->firstWhere('id', $userId);

        if ($person === null) {
            throw ValidationException::withMessages([
                'user_id' => 'Nhân sự này không nằm trong phạm vi báo cáo.',
            ]);
        }

        $row = $this->compute->computeForUser(
            $userId,
            (string) $person['name'],
            $version,
            $report->period_from->toDateString(),
            $report->period_to->toDateString(),
        );

        return ['detail' => $this->filterCriteria($row, $report)];
    }

    /**
     * Nhân sự thuộc phạm vi báo cáo.
     *
     * Báo cáo đã lưu đọc từ bản chụp lúc lưu — đó là điều giữ cho báo cáo cũ
     * mở lại ra đúng số cũ dù nhân sự đã nghỉ hoặc chuyển phòng. Còn nháp thì
     * lấy động để người tạo thấy ngay thay đổi.
     *
     * @return list<array{id: int, name: string}>
     */
    private function peopleFor(Report $report): array
    {
        if ($report->status === Report::STATUS_SAVED) {
            $snapshot = $report->peopleSnapshot
                ->map(fn ($row) => ['id' => (int) $row->user_id, 'name' => (string) $row->user_name])
                ->values()
                ->all();

            // Báo cáo lưu từ trước khi có bản chụp thì vẫn phải mở được, đành
            // lấy động như cũ.
            if ($snapshot !== []) {
                return $snapshot;
            }
        }

        return $this->livePeopleFor($report);
    }

    /**
     * Nhân sự đang hoạt động của phòng ban, thu hẹp theo bộ lọc đã chọn.
     *
     * @return list<array{id: int, name: string}>
     */
    private function livePeopleFor(Report $report): array
    {
        $members = $this->users->allActiveByDepartment((int) $report->department_id);
        $selected = $report->filteredUserIds();

        return $members
            ->when(
                $selected !== [],
                fn (Collection $rows) => $rows->filter(
                    fn ($user) => in_array((int) $user->id, $selected, true),
                ),
            )
            ->map(fn ($user) => ['id' => (int) $user->id, 'name' => (string) $user->name])
            ->values()
            ->all();
    }

    /**
     * Bỏ bớt các cột người tạo báo cáo không chọn hiển thị.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function applyColumns(array $row, Report $report): array
    {
        $enabled = $this->enabledColumns($report);

        foreach (array_keys(self::EVALUATION_COLUMNS) as $key) {
            if (in_array($key, $enabled, true)) {
                continue;
            }

            if ($key === 'classification') {
                unset($row['classification_code'], $row['classification_label']);

                continue;
            }

            unset($row[$key]);
        }

        // Bảng chỉ cần số tổng — chi tiết nằm ở màn hình từng nhân sự.
        unset($row['task_breakdown'], $row['event_breakdown']);

        return $row;
    }

    /**
     * Giữ lại phần ghi nhận hành vi thuộc các tiêu chí báo cáo có chọn.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function filterCriteria(array $row, Report $report): array
    {
        $criterionIds = $report->criteria
            ->pluck('criterion_id')
            ->filter()
            ->map(static fn ($id) => (int) $id)
            ->all();

        if ($criterionIds === []) {
            return $row;
        }

        $row['event_breakdown'] = array_values(array_filter(
            $row['event_breakdown'],
            static fn (array $event) => in_array((int) ($event['criterion_id'] ?? 0), $criterionIds, true),
        ));

        return $row;
    }

    /** @return list<string> */
    private function enabledColumns(Report $report): array
    {
        $columns = $report->columns
            ->sortBy('sort_order')
            ->pluck('column_key')
            ->all();

        return $columns !== [] ? $columns : self::DEFAULT_COLUMNS;
    }

    /** @param  array<string, mixed>  $data */
    private function syncRelations(Report $report, array $data): void
    {
        if (array_key_exists('viewer_user_ids', $data)) {
            $this->reports->syncViewers($report, $this->intList($data['viewer_user_ids']));
        }

        if (array_key_exists('filter_user_ids', $data)) {
            $this->reports->syncUserFilters($report, $this->intList($data['filter_user_ids']));
        }

        if (array_key_exists('column_keys', $data)) {
            $keys = array_values(array_filter(
                (array) $data['column_keys'],
                static fn ($key) => array_key_exists($key, self::EVALUATION_COLUMNS),
            ));
            $this->reports->syncColumns($report, $keys !== [] ? $keys : self::DEFAULT_COLUMNS);
        }

        if (array_key_exists('criterion_ids', $data)) {
            $this->reports->syncCriteria($report, $this->intList($data['criterion_ids']));
        }
    }

    /** @return list<int> */
    private function intList(mixed $values): array
    {
        return array_values(array_unique(array_map(
            static fn ($value) => (int) $value,
            array_filter((array) $values, static fn ($value) => (int) $value > 0),
        )));
    }

    /** @return array<string, mixed> */
    public function presentSummary(Report $report): array
    {
        return [
            'id' => $report->id,
            'department_id' => $report->department_id,
            'department_name' => $report->department?->name,
            'report_type' => $report->report_type,
            'title' => $report->title,
            'period_type' => $report->period_type,
            'period_from' => $report->period_from?->toDateString(),
            'period_to' => $report->period_to?->toDateString(),
            'status' => $report->status,
            'viewer_count' => $report->viewers->count(),
            'created_by_name' => $report->creator?->name,
            'created_at' => $report->created_at?->toIso8601String(),
            'updated_at' => $report->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function presentDetail(Report $report): array
    {
        $version = $report->evaluationConfigVersion;

        return array_merge($this->presentSummary($report), [
            'evaluation_config_version_id' => $report->evaluation_config_version_id,
            'version_no' => $version?->version_no,
            'version_published_at' => $version?->published_at?->toIso8601String(),
            'mode' => $version?->kit_snapshot['mode'] ?? null,
            'columns' => $this->enabledColumns($report),
            'column_labels' => self::EVALUATION_COLUMNS,
            'criterion_ids' => $report->criteria->pluck('criterion_id')->filter()->values()->all(),
            'filter_user_ids' => $report->filteredUserIds(),
            'viewers' => $report->viewers
                ->map(fn ($viewer) => [
                    'user_id' => (int) $viewer->user_id,
                    'name' => $viewer->user?->name,
                ])
                ->values()
                ->all(),
        ]);
    }
}
