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
use Modules\Report\App\Models\ReportDisplayRevision;
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
        'tasks' => 'Việc',
        'start_score' => 'Khởi đầu',
        'task_adjustment' => 'Từ việc',
        'bonus' => 'Cộng',
        'penalty' => 'Trừ',
        'final_score' => 'Cuối',
        'classification' => 'Xếp loại',
    ];

    public const DEFAULT_COLUMNS = [
        'tasks',
        'start_score',
        'task_adjustment',
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
                'display_revision' => ReportDisplayRevision::FIRST,
                'created_by' => (int) $actor->id,
                'updated_by' => (int) $actor->id,
            ]);

            $this->syncRelations($report, $data);
            $this->recordDisplayRevision(
                $report,
                ReportDisplayRevision::FIRST,
                ReportDisplayRevision::KIND_ORIGINAL,
                $this->intList($data['criterion_ids'] ?? []),
                $this->columnKeysFromData($data),
                (int) $actor->id,
                'Bản gốc lúc tạo',
            );

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

    public function delete(Report $report): void
    {
        if ($report->status === Report::STATUS_SAVED) {
            throw ValidationException::withMessages([
                'report' => 'Báo cáo đã lưu, không xoá được.',
            ]);
        }

        $this->reports->delete($report);
    }

    public function find(int $id): ?Report
    {
        return $this->reports->find($id);
    }

    /**
     * Cột điểm / cột tiêu chí đang áp dụng của báo cáo, để bảng chấm điểm
     * hiện đúng những gì đã chọn lúc tạo (hoặc phụ lục sau này).
     *
     * @return array<string, mixed>|null
     */
    public function displayFor(int $reportId, int $departmentId): ?array
    {
        $report = $this->reports->find($reportId);

        if (
            $report === null
            || (int) $report->department_id !== $departmentId
            || $report->report_type !== Report::TYPE_PERSONNEL_EVALUATION
        ) {
            return null;
        }

        return $this->presentDisplay($report);
    }

    /**
     * Đổi cột tiêu chí trên bảng chấm điểm — không ghi đè bản 1.0, lưu phụ lục
     * 1.1 / 1.2… Kỳ, phạm vi nhân sự và phiên bản khung chấm điểm giữ nguyên.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveDisplayAppendix(Report $report, User $actor, array $data): Report
    {
        if ($report->status === Report::STATUS_SAVED) {
            throw ValidationException::withMessages([
                'report' => 'Báo cáo đã lưu, không đổi cột tiêu chí được nữa.',
            ]);
        }

        $criterionIds = $this->intList($data['criterion_ids'] ?? []);
        $columnKeys = array_key_exists('column_keys', $data)
            ? $this->columnKeysFromData($data)
            : $this->enabledColumns($report);

        if ($this->sameDisplay($report, $criterionIds, $columnKeys)) {
            return $this->reports->find((int) $report->id) ?? $report;
        }

        return DB::transaction(function () use ($report, $actor, $criterionIds, $columnKeys) {
            $this->ensureOriginalRevision($report, (int) $actor->id);

            $next = $this->nextAppendixRevision($report);
            $this->reports->syncCriteria($report, $criterionIds);
            $this->reports->syncColumns($report, $columnKeys);
            $this->recordDisplayRevision(
                $report,
                $next,
                ReportDisplayRevision::KIND_APPENDIX,
                $criterionIds,
                $columnKeys,
                (int) $actor->id,
                'Phụ lục — đổi cột tiêu chí',
            );
            $this->reports->update($report, [
                'display_revision' => $next,
                'updated_by' => (int) $actor->id,
            ]);

            return $this->reports->find((int) $report->id) ?? $report;
        });
    }

    /**
     * Chốt lưu — từ đây kỳ báo cáo không ghi nhận / xoá điểm thêm được nữa.
     *
     * Chụp luôn danh sách nhân sự trong phạm vi: nếu vẫn lấy động theo phòng
     * ban thì người nghỉ việc sau kỳ sẽ biến mất khỏi báo cáo cũ, còn người
     * mới chuyển đến lại hiện ra trong kỳ họ chưa làm ở đó.
     */
    public function save(Report $report, User $actor): Report
    {
        if ($report->status === Report::STATUS_SAVED) {
            throw ValidationException::withMessages([
                'report' => 'Báo cáo đã được lưu trước đó.',
            ]);
        }

        return DB::transaction(function () use ($report, $actor) {
            $this->reports->syncPeopleSnapshot($report, $this->livePeopleFor($report));

            return $this->reports->update($report, [
                'status' => Report::STATUS_SAVED,
                'updated_by' => (int) $actor->id,
            ]);
        });
    }

    /**
     * Kỳ đang xem có bị khoá ghi nhận vì đã lưu báo cáo đánh giá nhân sự không.
     *
     * `locked` = cả khoảng đang xem nằm trong ít nhất một báo cáo đã lưu
     * (trường hợp chọn đúng một tháng đã chốt). `reports` liệt kê mọi báo cáo
     * giao với khoảng — để cảnh báo khi chỉ một phần kỳ bị khoá.
     *
     * @return array{locked: bool, reports: list<array{id: int, title: string, period_from: string, period_to: string}>}
     */
    public function periodLock(int $departmentId, string $from, string $to): array
    {
        $reports = $this->reports
            ->savedPersonnelOverlapping($departmentId, $from, $to)
            ->map(static fn (Report $report) => [
                'id' => (int) $report->id,
                'title' => (string) $report->title,
                'period_from' => $report->period_from?->toDateString() ?? '',
                'period_to' => $report->period_to?->toDateString() ?? '',
            ])
            ->values()
            ->all();

        $locked = false;
        foreach ($reports as $report) {
            if ($report['period_from'] <= $from && $report['period_to'] >= $to) {
                $locked = true;
                break;
            }
        }

        return [
            'locked' => $locked,
            'reports' => $reports,
        ];
    }

    /**
     * Chặn ghi nhận / xoá khi ngày phát sinh thuộc kỳ báo cáo đã lưu.
     */
    public function assertDateWritable(int $departmentId, string $date): void
    {
        $lock = $this->periodLock($departmentId, $date, $date);

        if (! $lock['locked']) {
            return;
        }

        $report = $lock['reports'][0];

        throw ValidationException::withMessages([
            'occurred_at' => 'Kỳ '.$report['period_from'].' – '.$report['period_to']
                .' đã lưu báo cáo "'.$report['title'].'", không ghi nhận hay xoá thêm được.',
        ]);
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

    public function canManage(Report $report, User $user): bool
    {
        if ($user->isSuperAdmin() || $user->allows('report.*')) {
            return true;
        }

        return $user->allowsScoped('report.manage_department', 'department', (int) $report->department_id);
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
    private function columnKeysFromData(array $data): array
    {
        $keys = array_values(array_filter(
            (array) ($data['column_keys'] ?? []),
            static fn ($key) => array_key_exists($key, self::EVALUATION_COLUMNS),
        ));

        return $keys !== [] ? $keys : self::DEFAULT_COLUMNS;
    }

    /**
     * @param  list<int>  $criterionIds
     * @param  list<string>  $columnKeys
     */
    private function recordDisplayRevision(
        Report $report,
        string $revision,
        string $kind,
        array $criterionIds,
        array $columnKeys,
        int $actorId,
        string $note,
    ): void {
        $this->reports->addDisplayRevision($report, [
            'revision' => $revision,
            'kind' => $kind,
            'criterion_ids' => array_values($criterionIds),
            'column_keys' => array_values($columnKeys),
            'note' => $note,
            'created_by' => $actorId,
        ]);
    }

    /**
     * Báo cáo tạo trước khi có bảng lịch sử: chụp trạng thái hiện tại thành 1.0
     * trước khi ghi phụ lục, để bản gốc không mất.
     */
    private function ensureOriginalRevision(Report $report, int $actorId): void
    {
        if ($report->displayRevisions->isNotEmpty()) {
            return;
        }

        $this->recordDisplayRevision(
            $report,
            ReportDisplayRevision::FIRST,
            ReportDisplayRevision::KIND_ORIGINAL,
            $this->intList($report->criteria->pluck('criterion_id')->all()),
            $this->enabledColumns($report),
            $actorId,
            'Bản gốc lúc tạo',
        );
        $report->load('displayRevisions.creator');
    }

    private function nextAppendixRevision(Report $report): string
    {
        $latest = $report->displayRevisions
            ->sortByDesc('id')
            ->first()
            ?->revision ?? $report->display_revision ?? ReportDisplayRevision::FIRST;

        $parts = explode('.', (string) $latest, 2);
        $major = ctype_digit($parts[0] ?? '') ? (int) $parts[0] : 1;
        $minor = ctype_digit($parts[1] ?? '') ? (int) $parts[1] : 0;

        return $major.'.'.($minor + 1);
    }

    /**
     * @param  list<int>  $criterionIds
     * @param  list<string>  $columnKeys
     */
    private function sameDisplay(Report $report, array $criterionIds, array $columnKeys): bool
    {
        $currentIds = $this->intList($report->criteria->pluck('criterion_id')->all());
        $currentCols = $this->enabledColumns($report);
        sort($currentIds);
        $nextIds = $criterionIds;
        sort($nextIds);
        $currentColsSorted = $currentCols;
        $nextCols = $columnKeys;
        sort($currentColsSorted);
        sort($nextCols);

        return $currentIds === $nextIds && $currentColsSorted === $nextCols;
    }

    /** @return array<string, mixed> */
    public function presentDisplay(Report $report): array
    {
        $latest = $report->displayRevisions->sortByDesc('id')->first();

        return [
            'id' => (int) $report->id,
            'title' => (string) $report->title,
            'status' => (string) $report->status,
            'period_from' => $report->period_from?->toDateString() ?? '',
            'period_to' => $report->period_to?->toDateString() ?? '',
            'revision' => (string) ($report->display_revision ?: ReportDisplayRevision::FIRST),
            'kind' => $latest?->kind ?? ReportDisplayRevision::KIND_ORIGINAL,
            'criterion_ids' => $this->intList($report->criteria->pluck('criterion_id')->all()),
            'column_keys' => $this->enabledColumns($report),
            'revisions' => $report->displayRevisions
                ->map(fn (ReportDisplayRevision $item) => [
                    'revision' => $item->revision,
                    'kind' => $item->kind,
                    'note' => $item->note,
                    'criterion_ids' => $this->intList($item->criterion_ids ?? []),
                    'column_keys' => array_values((array) ($item->column_keys ?? [])),
                    'created_by_name' => $item->creator?->name,
                    'created_at' => $item->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
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
            'revision' => (string) ($report->display_revision ?: ReportDisplayRevision::FIRST),
            'revision_kind' => $report->displayRevisions->sortByDesc('id')->first()?->kind
                ?? ReportDisplayRevision::KIND_ORIGINAL,
            'viewer_count' => $report->viewers->count(),
            'viewer_names' => $report->viewers
                ->map(static fn ($viewer) => $viewer->user?->name)
                ->filter()
                ->values()
                ->all(),
            'filter_user_count' => count($report->filteredUserIds()),
            'column_count' => count($this->enabledColumns($report)),
            'criterion_count' => $report->criteria->count(),
            'created_by' => $report->created_by,
            'created_by_name' => $report->creator?->name,
            'created_by_email' => $report->creator?->email,
            'created_by_avatar_url' => $report->creator?->avatar_url,
            'created_by_department' => $report->creator?->department?->name,
            'updated_by_name' => $report->updater?->name,
            'updated_by_email' => $report->updater?->email,
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
            'display' => $this->presentDisplay($report),
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
