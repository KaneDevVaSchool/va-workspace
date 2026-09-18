<?php

namespace Modules\Evaluation\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Evaluation\App\Http\Requests\UpdateEvaluationScoreKitRequest;
use Modules\Evaluation\App\Models\EvaluationScoreKit;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationScoreKitRepositoryInterface;
use Modules\Evaluation\App\Services\EvaluationConfigVersionService;
use Modules\Evaluation\App\Services\EvaluationScoreKitService;
use Modules\Identity\App\Services\ActivityLogService;
use Modules\Identity\App\Services\PermissionService;

/**
 * Manager JSON:
 *   GET  /api/evaluation/score-kit               — khung chấm điểm phòng ban user
 *   PUT  /api/evaluation/score-kit                — lưu cách tính (evaluation.manage_department)
 *   GET  /api/evaluation/score-kit/quality-levels — thang chất lượng CHỈ ĐỌC của
 *        một phòng ban bất kỳ (?department_id=), dùng cho form chấm điểm việc ở
 *        module Project — KHÔNG yêu cầu evaluation.manage_department vì đây không
 *        phải màn hình quản lý khung chấm điểm, chỉ đọc tối thiểu mode + quality_levels
 *        để đồng bộ với cách EvaluationScoreComputeService tính điểm.
 */
class EvaluationScoreKitController extends Controller
{
    /** @var array<string, string> */
    private const SCALAR_FIELD_LABELS = [
        'mode' => 'Cách tính điểm',
        'base_score' => 'Điểm khởi đầu',
        'task_base_score' => 'Điểm cơ bản mỗi việc',
        'quality_bonus_percent' => 'Bonus chất lượng xuất sắc',
        'points_per_completed_task' => 'Điểm mỗi việc hoàn thành',
        'points_per_incomplete_task' => 'Điểm mỗi việc chưa hoàn thành',
        'use_project_importance' => 'Nhân mức độ dự án',
        'classification_criterion_id' => 'Thang phân loại',
        'difficulty_criterion_id' => 'Tiêu chí nguồn · Độ khó',
        'progress_criterion_id' => 'Tiêu chí nguồn · Tiến độ',
        'quality_criterion_id' => 'Tiêu chí nguồn · Chất lượng',
        'classification_use_default' => 'Dùng mặc định · Xếp loại',
        'difficulty_use_default' => 'Dùng mặc định · Độ khó',
        'progress_use_default' => 'Dùng mặc định · Tiến độ',
        'quality_use_default' => 'Dùng mặc định · Chất lượng',
    ];

    /** @var array<string, string> */
    private const FORMULA_FIELD_LABELS = [
        'base' => 'Công thức · Điểm khởi đầu',
        'done' => 'Công thức · Việc hoàn thành',
        'undone' => 'Công thức · Việc chưa hoàn thành',
        'weight' => 'Công thức · Độ khó việc',
        'project' => 'Công thức · Mức độ dự án',
        'progress' => 'Công thức · Tiến độ',
        'quality' => 'Công thức · Chất lượng',
        'contrib' => 'Công thức · Trọng số đóng góp',
        'lock_difficulty' => 'Công thức · Khóa độ khó',
    ];

    /** @var array<string, string> */
    private const LEVEL_PROPERTY_LABELS = [
        'code' => 'Mã',
        'label' => 'Tên mức',
        'score' => 'Điểm / hệ số',
        'sort_order' => 'Thứ tự',
    ];

    public function __construct(
        private readonly EvaluationScoreKitService $service,
        private readonly EvaluationConfigVersionService $configVersions,
        private readonly PermissionService $permissions,
        private readonly ActivityLogService $activityLogs,
        private readonly EvaluationScoreKitRepositoryInterface $kits,
    ) {}

    /**
     * Thang chất lượng CHỈ ĐỌC của một phòng ban — dùng cho form chấm điểm
     * việc (Modules/Project) chọn đúng theo khung chấm điểm "Cách 2 — Hiệu
     * suất việc" thay vì gõ text tự do, để khớp với cách
     * EvaluationScoreComputeService::qualityFactor() tính điểm.
     *
     * Chỉ yêu cầu đăng nhập — không cần evaluation.manage_department, vì đây
     * không phải màn hình quản lý (không đọc/ghi công thức, điểm cộng-trừ
     * hay dữ liệu nhạy cảm khác của phòng ban, chỉ trả đúng mode + quality_levels).
     * Không có "Cách 2" (chưa cấu hình, hoặc đang dùng "Cách 1") → trả
     * quality_levels rỗng, để frontend tự fallback về ô text tự do.
     */
    public function qualityLevels(Request $request): JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Bạn cần đăng nhập.'], 401);
        }

        $departmentId = (int) $request->query('department_id');
        if ($departmentId <= 0) {
            return response()->json(['message' => 'Thiếu department_id.'], 422);
        }

        $kitScales = $this->service->taskScalesForDepartment($departmentId);
        $isWeightedTask = $kitScales['mode'] === EvaluationScoreKit::MODE_WEIGHTED_TASK;

        return response()->json([
            'department_id' => $departmentId,
            'mode' => $kitScales['mode'],
            'lock_difficulty' => $kitScales['lock_difficulty'],
            'formula' => $kitScales['formula'],
            'difficulty_levels' => $isWeightedTask ? $kitScales['difficulty_levels'] : [],
            'progress_levels' => $isWeightedTask ? $kitScales['progress_levels'] : [],
            'quality_levels' => $isWeightedTask ? $kitScales['quality_levels'] : [],
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền xem khung chấm điểm.'], 403);
        }

        return response()->json($this->service->showForDepartment($departmentId));
    }

    public function update(UpdateEvaluationScoreKitRequest $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền cập nhật khung chấm điểm.'], 403);
        }

        $data = $request->validated();
        $context = (string) ($data['change_context'] ?? 'manual');
        unset($data['change_context']);

        $kit = DB::transaction(function () use ($request, $departmentId, $data, $context): array {
            $before = $this->service->showForDepartment($departmentId)['kit'];
            $kit = $this->service->upsert(
                $departmentId,
                (int) $request->user()->id,
                $data,
            );

            $created = $before['id'] === null;
            $changes = $this->activityChanges($before, $kit);
            $activeVersion = $this->configVersions->activeForDepartment($departmentId);
            $activeKit = is_array($activeVersion?->kit_snapshot)
                ? $activeVersion->kit_snapshot
                : null;
            if ($activeKit !== null) {
                unset($activeKit['difficulty_lookup']);
            }
            $versionDrifted = $activeKit === null || ! $this->sameSnapshotValue($activeKit, $kit);

            if ($created || $changes !== []) {
                $this->activityLogs->record(
                    $this->activityAction($created, $changes, $context),
                    $this->activityDescription($kit, $changes, $context, $created),
                    $request->user(),
                    'evaluation_score_kit',
                    $kit['id'] !== null ? (int) $kit['id'] : null,
                    [
                        'department_id' => $departmentId,
                        'change_context' => $this->contextLabel($context),
                        'changed_fields' => array_values(array_map(
                            fn (array $change) => $change['label'],
                            $changes,
                        )),
                        'changes' => $changes,
                    ],
                );
            }

            if ($created || $changes !== [] || $versionDrifted) {
                $this->configVersions->publish(
                    $departmentId,
                    (int) $request->user()->id,
                    'Tự động chốt khi cập nhật khung chấm điểm',
                );
            }

            return $kit;
        });

        return response()->json(['kit' => $kit]);
    }

    private function departmentIdOrFail(Request $request): int|JsonResponse
    {
        $departmentId = $request->user()?->department_id;

        return $departmentId
            ? (int) $departmentId
            : response()->json(['message' => 'Tài khoản chưa gắn với phòng ban nào.'], 422);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{label: string, before: mixed, after: mixed}>
     */
    private function activityChanges(array $before, array $after): array
    {
        $changes = [];

        foreach (self::SCALAR_FIELD_LABELS as $field => $label) {
            $oldValue = $before[$field] ?? null;
            $newValue = $after[$field] ?? null;
            if ($this->sameAuditValue($oldValue, $newValue)) {
                continue;
            }

            $changes[$field] = [
                'label' => $label,
                'before' => $oldValue,
                'after' => $newValue,
            ];
        }

        $beforeFormula = is_array($before['formula'] ?? null) ? $before['formula'] : [];
        $afterFormula = is_array($after['formula'] ?? null) ? $after['formula'] : [];
        foreach (self::FORMULA_FIELD_LABELS as $key => $label) {
            $oldValue = $beforeFormula[$key] ?? null;
            $newValue = $afterFormula[$key] ?? null;
            if ($this->sameAuditValue($oldValue, $newValue)) {
                continue;
            }

            $changes['formula.'.$key] = [
                'label' => $label,
                'before' => $oldValue,
                'after' => $newValue,
            ];
        }

        $this->appendLevelChanges(
            $changes,
            'base_adjust_levels',
            'Thang xếp loại',
            $before['base_adjust_levels'] ?? [],
            $after['base_adjust_levels'] ?? [],
        );
        $this->appendLevelChanges(
            $changes,
            'weighted_task_levels',
            'Thang độ khó',
            $before['weighted_task_levels'] ?? [],
            $after['weighted_task_levels'] ?? [],
        );
        $this->appendLevelChanges(
            $changes,
            'progress_levels',
            'Thang tiến độ',
            $before['progress_levels'] ?? [],
            $after['progress_levels'] ?? [],
        );
        $this->appendLevelChanges(
            $changes,
            'quality_levels',
            'Thang chất lượng',
            $before['quality_levels'] ?? [],
            $after['quality_levels'] ?? [],
        );
        $this->appendLevelChanges(
            $changes,
            'performance_levels',
            'Thang xếp loại',
            $before['performance_levels'] ?? [],
            $after['performance_levels'] ?? [],
        );

        return $changes;
    }

    /**
     * @param  array<string, array{label: string, before: mixed, after: mixed}>  $changes
     */
    private function appendLevelChanges(
        array &$changes,
        string $field,
        string $groupLabel,
        mixed $beforeLevels,
        mixed $afterLevels,
    ): void {
        $beforeRows = is_array($beforeLevels) ? array_values($beforeLevels) : [];
        $afterRows = is_array($afterLevels) ? array_values($afterLevels) : [];
        $rowCount = max(count($beforeRows), count($afterRows));

        for ($index = 0; $index < $rowCount; $index++) {
            $beforeRow = is_array($beforeRows[$index] ?? null) ? $beforeRows[$index] : [];
            $afterRow = is_array($afterRows[$index] ?? null) ? $afterRows[$index] : [];

            foreach (self::LEVEL_PROPERTY_LABELS as $property => $propertyLabel) {
                $oldValue = $beforeRow[$property] ?? null;
                $newValue = $afterRow[$property] ?? null;
                if ($this->sameAuditValue($oldValue, $newValue)) {
                    continue;
                }

                $path = $field.'.'.$index.'.'.$property;
                $changes[$path] = [
                    'label' => $groupLabel.' · Mức '.($index + 1).' · '.$propertyLabel,
                    'before' => $oldValue,
                    'after' => $newValue,
                ];
            }
        }
    }

    private function sameAuditValue(mixed $before, mixed $after): bool
    {
        if (is_numeric($before) && is_numeric($after)) {
            return abs((float) $before - (float) $after) < 0.00001;
        }

        return json_encode($before, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)
            === json_encode($after, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    private function sameSnapshotValue(mixed $before, mixed $after): bool
    {
        if (is_numeric($before) && is_numeric($after)) {
            return abs((float) $before - (float) $after) < 0.00001;
        }

        if (! is_array($before) || ! is_array($after)) {
            return $before === $after;
        }

        if (count($before) !== count($after)) {
            return false;
        }

        $beforeKeys = array_keys($before);
        $afterKeys = array_keys($after);
        sort($beforeKeys);
        sort($afterKeys);
        if ($beforeKeys !== $afterKeys) {
            return false;
        }

        foreach ($before as $key => $value) {
            if (! $this->sameSnapshotValue($value, $after[$key])) {
                return false;
            }
        }

        return true;
    }

    /** @param  array<string, array{label: string, before: mixed, after: mixed}>  $changes */
    private function activityAction(bool $created, array $changes, string $context): string
    {
        if ($created) {
            return 'evaluation_score_kit.create';
        }
        if (array_key_exists('mode', $changes)) {
            return 'evaluation_score_kit.mode_change';
        }
        if ($context === 'reset' && $this->isResetToDefaults($changes)) {
            return 'evaluation_score_kit.reset';
        }

        return 'evaluation_score_kit.update';
    }

    /**
     * @param  array<string, mixed>  $kit
     * @param  array<string, array{label: string, before: mixed, after: mixed}>  $changes
     */
    private function activityDescription(array $kit, array $changes, string $context, bool $created): string
    {
        $prefix = match (true) {
            $created => 'Thiết lập khung chấm điểm',
            array_key_exists('mode', $changes) => 'Đổi cách tính điểm',
            $context === 'reset' && $this->isResetToDefaults($changes) => 'Khôi phục mặc định khung chấm điểm',
            default => 'Cập nhật khung chấm điểm',
        };
        $mode = match ($kit['mode'] ?? null) {
            'base_adjust' => 'đếm số việc',
            'weighted_task' => 'hiệu suất việc',
            default => 'chưa chọn cách tính',
        };
        $fieldLabels = array_values(array_unique(array_map(
            fn (array $change) => $change['label'],
            $changes,
        )));
        $visibleLabels = array_slice($fieldLabels, 0, 4);
        $remaining = count($fieldLabels) - count($visibleLabels);
        $detail = $visibleLabels !== [] ? '; thay đổi: '.implode(', ', $visibleLabels) : '';
        if ($remaining > 0) {
            $detail .= ' và '.$remaining.' mục khác';
        }

        return $prefix.' ('.$mode.')'.$detail;
    }

    /** @param  array<string, array{label: string, before: mixed, after: mixed}>  $changes */
    private function isResetToDefaults(array $changes): bool
    {
        $scalarDefaults = [
            'base_score' => 100.0,
            'task_base_score' => 100.0,
            'quality_bonus_percent' => 5.0,
            'points_per_completed_task' => 0.0,
            'points_per_incomplete_task' => 0.0,
            'use_project_importance' => false,
            'classification_criterion_id' => null,
            'difficulty_criterion_id' => null,
            'progress_criterion_id' => null,
            'quality_criterion_id' => null,
            'classification_use_default' => true,
            'difficulty_use_default' => true,
            'progress_use_default' => true,
            'quality_use_default' => true,
        ];
        $formulaDefaults = EvaluationScoreKit::defaultFormula();
        $levelDefaults = [
            'base_adjust_levels' => EvaluationScoreKit::defaultBaseAdjustLevels(),
            'weighted_task_levels' => EvaluationScoreKit::defaultWeightedTaskLevels(),
            'progress_levels' => EvaluationScoreKit::defaultProgressLevels(),
            'quality_levels' => EvaluationScoreKit::defaultQualityLevels(),
            'performance_levels' => EvaluationScoreKit::defaultPerformanceLevels(),
        ];

        foreach ($changes as $field => $change) {
            if (array_key_exists($field, $scalarDefaults)) {
                if (! $this->sameAuditValue($change['after'], $scalarDefaults[$field])) {
                    return false;
                }

                continue;
            }

            if (str_starts_with($field, 'formula.')) {
                $key = substr($field, strlen('formula.'));
                if (! array_key_exists($key, $formulaDefaults)
                    || ! $this->sameAuditValue($change['after'], $formulaDefaults[$key])) {
                    return false;
                }

                continue;
            }

            if (preg_match('/^(base_adjust_levels|weighted_task_levels|progress_levels|quality_levels|performance_levels)\.(\d+)\.(code|label|score|sort_order)$/', $field, $matches) === 1) {
                $group = $matches[1];
                $index = (int) $matches[2];
                $property = $matches[3];
                $default = $levelDefaults[$group][$index][$property] ?? null;
                if (! $this->sameAuditValue($change['after'], $default)) {
                    return false;
                }

                continue;
            }

            if ($field !== 'mode') {
                return false;
            }
        }

        return $changes !== [];
    }

    private function contextLabel(string $context): string
    {
        return match ($context) {
            'mode_change' => 'Đổi cách tính',
            'reset' => 'Khôi phục mặc định',
            'leave_save' => 'Lưu trước khi rời trang',
            default => 'Chỉnh sửa thủ công',
        };
    }
}
