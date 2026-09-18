<?php

namespace Modules\Project\App\Services;

use App\Models\User;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Models\EvaluationScoreKit;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationCriteriaRepositoryInterface;
use Modules\Evaluation\App\Services\EvaluationScoreKitService;
use Modules\Project\App\Enums\ProjectEnums;
use Modules\Project\App\Enums\TaskEnums;
use Modules\Project\App\Models\Project;

/**
 * Mức độ / độ khó trên công việc.
 *
 * Ưu tiên thang độ khó của khung điểm "Hiệu suất việc" để lúc giao việc
 * chọn đúng mức mà máy sẽ tra khi chấm kỳ. Không có Cách 2 thì rơi về
 * tiêu chí gắn cờ use_for_task_type, rồi bộ 5 bậc cứng.
 */
class TaskImportanceOptions
{
    public function __construct(
        private readonly EvaluationCriteriaRepositoryInterface $criteria,
        private readonly EvaluationScoreKitService $scoreKits,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(?User $user): array
    {
        $departmentId = $user?->department_id ? (int) $user->department_id : null;

        return $this->forDepartment($departmentId);
    }

    /**
     * @return list<string>
     */
    public function acceptedValuesForContext(
        ?int $projectId = null,
        ?int $userDepartmentId = null,
        ?int $originDepartmentId = null,
    ): array {
        $departmentId = $originDepartmentId
            ?? $this->departmentIdFromProject($projectId)
            ?? $userDepartmentId;

        return $this->acceptedValues($departmentId);
    }

    /** @return list<string> */
    public function acceptedValues(?int $departmentId): array
    {
        $values = TaskEnums::acceptedPriorities();
        foreach ($this->forDepartment($departmentId)['importance'] as $opt) {
            foreach (['value', 'code', 'label'] as $key) {
                $raw = trim((string) ($opt[$key] ?? ''));
                if ($raw !== '') {
                    $values[] = $raw;
                }
            }
        }

        return array_values(array_unique($values));
    }

    public function matchAccepted(?string $input, ?int $departmentId): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $lower = mb_strtolower(trim($input));
        foreach ($this->forDepartment($departmentId)['importance'] as $opt) {
            foreach (['value', 'code', 'label'] as $key) {
                $raw = mb_strtolower(trim((string) ($opt[$key] ?? '')));
                if ($raw !== '' && $raw === $lower) {
                    return (string) $opt['value'];
                }
            }
        }

        $mapped = TaskEnums::priorityFromInput($input);
        if ($mapped !== null) {
            return $mapped;
        }

        return null;
    }

    private function departmentIdFromProject(?int $projectId): ?int
    {
        if (! $projectId) {
            return null;
        }

        $project = Project::query()->find($projectId);

        return $project?->owner_department_id
            ?? $project?->executing_department_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function forDepartment(?int $departmentId): array
    {
        $scales = $departmentId
            ? $this->scoreKits->taskScalesForDepartment($departmentId)
            : $this->emptyScales();

        if (($scales['mode'] ?? null) === EvaluationScoreKit::MODE_WEIGHTED_TASK) {
            $fromKit = $this->mapLevels($scales['difficulty_levels'] ?? [], canonicalize: false);
            if ($fromKit !== []) {
                $criterionId = (int) ($scales['difficulty_criterion_id'] ?? 0);
                $criterion = $criterionId > 0 ? $this->criteria->find($criterionId) : null;

                return array_merge($scales, [
                    'source' => 'kit_difficulty',
                    'criterion' => $criterion instanceof EvaluationCriteria
                        ? [
                            'id' => $criterion->id,
                            'name' => $criterion->name,
                            'description' => $criterion->description,
                        ]
                        : [
                            'id' => null,
                            'name' => 'Độ khó',
                            'description' => 'Chọn lúc giao việc. Hệ số lấy từ khung chấm điểm phòng ban.',
                        ],
                    'importance' => $fromKit,
                ]);
            }
        }

        $criterion = $departmentId ? $this->criteria->findTaskTypeCriterion($departmentId) : null;
        if ($criterion instanceof EvaluationCriteria) {
            $levels = $this->mapLevels($criterion->levels ?? [], canonicalize: true);

            return array_merge($scales, [
                'source' => 'task_type',
                'criterion' => [
                    'id' => $criterion->id,
                    'name' => $criterion->name,
                    'description' => $criterion->description,
                ],
                'importance' => $levels !== [] ? $levels : $this->fallbackOptions(),
            ]);
        }

        return array_merge($scales, [
            'source' => 'fallback',
            'criterion' => null,
            'importance' => $this->fallbackOptions(),
        ]);
    }

    /**
     * @param  list<array{code?: string, label?: string, description?: string, score?: int|float}>  $levels
     * @return list<array{value: string, label: string, description: string, weight: int|float, code: string}>
     */
    private function mapLevels(array $levels, bool $canonicalize = true): array
    {
        $options = [];
        foreach ($levels as $level) {
            $code = trim((string) ($level['code'] ?? ''));
            $label = trim((string) ($level['label'] ?? ''));
            if ($code === '' && $label === '') {
                continue;
            }

            $value = $code !== '' ? $code : $label;
            if ($canonicalize) {
                $value = ProjectEnums::importanceFromInput($code !== '' ? $code : $label)
                    ?? ProjectEnums::importanceFromInput($label)
                    ?? $value;
            }

            $options[] = [
                'value' => $value,
                'label' => $label !== '' ? $label : $value,
                'description' => trim((string) ($level['description'] ?? '')),
                'weight' => $level['score'] ?? (ProjectEnums::IMPORTANCE_WEIGHTS[$value] ?? 0),
                'code' => $code,
            ];
        }

        return $options;
    }

    /** @return list<array{value: string, label: string, description: string, weight: int, code: string}> */
    private function fallbackOptions(): array
    {
        return array_map(function (array $row) {
            return [
                'value' => $row['value'],
                'label' => $row['label'],
                'description' => $row['description'] ?? '',
                'weight' => $row['weight'] ?? 0,
                'code' => '',
            ];
        }, ProjectEnums::options()['importance']);
    }

    /** @return array<string, mixed> */
    private function emptyScales(): array
    {
        return [
            'mode' => null,
            'lock_difficulty' => false,
            'formula' => ['weight' => 'off', 'progress' => 'off', 'quality' => 'off'],
            'difficulty_criterion_id' => null,
            'difficulty_levels' => [],
            'progress_levels' => [],
            'quality_levels' => [],
        ];
    }
}
