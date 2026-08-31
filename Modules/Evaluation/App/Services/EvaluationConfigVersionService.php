<?php

namespace Modules\Evaluation\App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Evaluation\App\Models\EvaluationConfigVersion;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationConfigVersionRepositoryInterface;
use Modules\Project\App\Enums\ProjectEnums;

/**
 * Chốt phiên bản cấu hình đánh giá của phòng ban.
 *
 * Khung chấm điểm và tiêu chí đánh giá được sửa trực tiếp (ghi đè tại chỗ).
 * Báo cáo cần điểm cũ không đổi khi cấu hình đổi, nên trước khi tạo báo cáo
 * hệ thống chụp lại toàn bộ cấu hình thành một phiên bản bất biến và báo cáo
 * trỏ tới phiên bản đó.
 */
class EvaluationConfigVersionService
{
    public function __construct(
        private readonly EvaluationConfigVersionRepositoryInterface $versions,
        private readonly EvaluationScoreKitService $kits,
        private readonly EvaluationCriteriaService $criteria,
    ) {}

    /**
     * Chụp cấu hình hiện tại thành phiên bản mới và cho áp dụng ngay.
     * Phiên bản đang áp dụng trước đó chuyển sang trạng thái cũ.
     */
    public function publish(
        int $departmentId,
        int $userId,
        ?string $notes = null,
        ?string $effectiveFrom = null,
    ): EvaluationConfigVersion {
        $kitSnapshot = $this->kits->showForDepartment($departmentId)['kit'];
        $allCriteria = $this->criteria->listForDepartment($departmentId);
        $criteriaSnapshot = $allCriteria
            ->filter(fn (array $criterion) => ! empty($criterion['is_active']))
            ->values()
            ->all();

        $kitSnapshot['difficulty_lookup'] = $this->difficultyLookup($kitSnapshot, $allCriteria);

        return DB::transaction(function () use (
            $departmentId,
            $userId,
            $notes,
            $effectiveFrom,
            $kitSnapshot,
            $criteriaSnapshot,
        ) {
            $this->versions->supersedeActive($departmentId);

            return $this->versions->create([
                'department_id' => $departmentId,
                'version_no' => $this->versions->maxVersionNo($departmentId) + 1,
                'status' => EvaluationConfigVersion::STATUS_ACTIVE,
                'kit_snapshot' => $kitSnapshot,
                'criteria_snapshot' => $criteriaSnapshot,
                'notes' => $notes !== null && trim($notes) !== ''
                    ? mb_substr(trim($notes), 0, 500)
                    : null,
                'published_by' => $userId,
                'published_at' => now(),
                'effective_from' => $effectiveFrom !== null && $effectiveFrom !== ''
                    ? $effectiveFrom
                    : now()->toDateString(),
            ]);
        });
    }

    /**
     * Bảng tra "giá trị độ khó ghi trên công việc" => hệ số.
     *
     * Cần bảng riêng vì hai đầu không nói cùng một thứ tiếng: thang độ khó
     * trong khung chấm điểm lưu mã / tên mức (`TB`, `Trung bình`), còn
     * `task.priority` lưu giá trị đã quy chuẩn của tiêu chí loại công việc
     * (`important`, `high_priority`... — xem TaskImportanceOptions::mapLevels).
     * Chụp sẵn mọi dạng viết của cùng một mức vào bản chụp thì engine tra
     * thẳng, không phải đọc lại cấu hình sống lúc tính điểm.
     *
     * @param  array<string, mixed>  $kitSnapshot
     * @param  Collection<int, array<string, mixed>>  $criteria
     * @return array<string, float>
     */
    private function difficultyLookup(array $kitSnapshot, Collection $criteria): array
    {
        $levels = is_array($kitSnapshot['weighted_task_levels'] ?? null)
            ? $kitSnapshot['weighted_task_levels']
            : [];

        // Thang độ khó lấy từ tiêu chí nào thì tra theo đúng tiêu chí đó; phòng
        // ban không chọn thì rơi về tiêu chí loại công việc, vì đó là nguồn
        // thực sự sinh ra giá trị đang nằm trên `task.priority`.
        $criterionId = $kitSnapshot['difficulty_criterion_id'] ?? null;
        $source = $criterionId !== null
            ? $criteria->first(fn (array $c) => (int) ($c['id'] ?? 0) === (int) $criterionId)
            : null;
        $source ??= $criteria->first(fn (array $c) => ! empty($c['use_for_task_type']));

        $sourceLevels = is_array($source['levels'] ?? null) ? array_values($source['levels']) : [];

        $lookup = [];
        foreach (array_values($levels) as $index => $level) {
            if (! is_array($level)) {
                continue;
            }

            $score = (float) ($level['score'] ?? 1);
            $aliases = [
                (string) ($level['code'] ?? ''),
                (string) ($level['label'] ?? ''),
            ];

            // Cùng vị trí trong thang = cùng một mức: lấy thêm mã/tên bên tiêu
            // chí nguồn và giá trị quy chuẩn mà Task thực sự lưu.
            $origin = $sourceLevels[$index] ?? null;
            if (is_array($origin)) {
                $originCode = (string) ($origin['code'] ?? '');
                $originLabel = (string) ($origin['label'] ?? '');
                $aliases[] = $originCode;
                $aliases[] = $originLabel;
                $aliases[] = (string) (ProjectEnums::importanceFromInput(
                    $originCode !== '' ? $originCode : $originLabel,
                ) ?? '');
                $aliases[] = (string) (ProjectEnums::importanceFromInput($originLabel) ?? '');
            }

            foreach ($aliases as $alias) {
                $key = mb_strtolower(trim($alias));
                if ($key !== '' && ! array_key_exists($key, $lookup)) {
                    $lookup[$key] = $score;
                }
            }
        }

        return $lookup;
    }

    public function activeForDepartment(int $departmentId): ?EvaluationConfigVersion
    {
        return $this->versions->activeForDepartment($departmentId);
    }

    /**
     * Phiên bản đang áp dụng, tự chốt phiên bản đầu tiên nếu phòng ban chưa
     * từng chốt — báo cáo không bị chặn chỉ vì thiếu thao tác thủ công này.
     */
    public function activeOrPublish(int $departmentId, int $userId): EvaluationConfigVersion
    {
        return $this->versions->activeForDepartment($departmentId)
            ?? $this->publish($departmentId, $userId, 'Tự động chốt khi tạo báo cáo đầu tiên');
    }

    public function find(int $versionId): ?EvaluationConfigVersion
    {
        return $this->versions->find($versionId);
    }

    /** @return Collection<int, EvaluationConfigVersion> */
    public function listForDepartment(int $departmentId): Collection
    {
        return $this->versions->allByDepartment($departmentId);
    }

    /**
     * Payload JSON cho frontend.
     *
     * @return array<string, mixed>
     */
    public function present(EvaluationConfigVersion $version, bool $withSnapshots = false): array
    {
        $row = [
            'id' => $version->id,
            'department_id' => $version->department_id,
            'version_no' => $version->version_no,
            'status' => $version->status,
            'notes' => $version->notes,
            'published_by' => $version->published_by,
            'published_by_name' => $version->publisher?->name,
            'published_at' => $version->published_at?->toIso8601String(),
            'effective_from' => $version->effective_from?->toDateString(),
            'criteria_count' => count($version->criteria_snapshot ?? []),
            'mode' => $version->kit_snapshot['mode'] ?? null,
        ];

        if ($withSnapshots) {
            $row['kit_snapshot'] = $version->kit_snapshot;
            $row['criteria_snapshot'] = $version->criteria_snapshot;
        }

        return $row;
    }
}
