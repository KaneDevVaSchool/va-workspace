<?php

namespace Modules\Project\App\Services;

use App\Models\User;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationCriteriaRepositoryInterface;
use Modules\Project\App\Enums\ProjectEnums;

/**
 * Mức độ quan trọng / loại công việc lấy từ tiêu chí mà phòng ban
 * đã gán (cờ use_for_task_type). Mỗi phòng tự tạo tiêu chí và tự gán.
 * Chưa gán thì fallback bộ 5 bậc cứng của ProjectEnums.
 */
class TaskImportanceOptions
{
    public function __construct(
        private readonly EvaluationCriteriaRepositoryInterface $criteria,
    ) {}

    /**
     * @return array{
     *     criterion: array{id: int, name: string, description: string|null}|null,
     *     importance: list<array{value: string, label: string, description: string, weight: int|float, code: string}>
     * }
     */
    public function forUser(?User $user): array
    {
        $departmentId = $user?->department_id ? (int) $user->department_id : null;

        return $this->forDepartment($departmentId);
    }

    /**
     * @return array{
     *     criterion: array{id: int, name: string, description: string|null}|null,
     *     importance: list<array{value: string, label: string, description: string, weight: int|float, code: string}>
     * }
     */
    public function forDepartment(?int $departmentId): array
    {
        $criterion = $departmentId ? $this->criteria->findTaskTypeCriterion($departmentId) : null;
        if (! $criterion instanceof EvaluationCriteria) {
            return [
                'criterion' => null,
                'importance' => $this->fallbackOptions(),
            ];
        }

        $levels = $this->mapLevels($criterion->levels ?? []);

        return [
            'criterion' => [
                'id' => $criterion->id,
                'name' => $criterion->name,
                'description' => $criterion->description,
            ],
            'importance' => $levels !== [] ? $levels : $this->fallbackOptions(),
        ];
    }

    /**
     * @param  list<array{code?: string, label?: string, description?: string, score?: int|float}>  $levels
     * @return list<array{value: string, label: string, description: string, weight: int|float, code: string}>
     */
    private function mapLevels(array $levels): array
    {
        $options = [];
        foreach ($levels as $level) {
            $code = trim((string) ($level['code'] ?? ''));
            $label = trim((string) ($level['label'] ?? ''));
            if ($code === '' && $label === '') {
                continue;
            }

            $value = ProjectEnums::importanceFromInput($code !== '' ? $code : $label)
                ?? ProjectEnums::importanceFromInput($label)
                ?? ($code !== '' ? $code : $label);

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
}
