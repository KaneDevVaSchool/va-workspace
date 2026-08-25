<?php

namespace Modules\Evaluation\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationCriteriaRepositoryInterface;

class EvaluationCriteriaRepository implements EvaluationCriteriaRepositoryInterface
{
    public function allByDepartment(int $departmentId): Collection
    {
        return EvaluationCriteria::query()
            ->with('criterionType')
            ->where('department_id', $departmentId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): ?EvaluationCriteria
    {
        return EvaluationCriteria::query()->find($id);
    }

    public function findByDepartment(int $id, int $departmentId): ?EvaluationCriteria
    {
        return EvaluationCriteria::query()
            ->with('criterionType')
            ->where('id', $id)
            ->where('department_id', $departmentId)
            ->first();
    }

    public function create(array $data): EvaluationCriteria
    {
        $criterion = EvaluationCriteria::query()->create($data);
        $criterion->load('criterionType');

        return $criterion;
    }

    public function update(EvaluationCriteria $criterion, array $data): EvaluationCriteria
    {
        $criterion->update($data);

        return $criterion->fresh(['criterionType']);
    }

    public function delete(EvaluationCriteria $criterion): bool
    {
        return (bool) $criterion->delete();
    }

    public function toggleActive(EvaluationCriteria $criterion): EvaluationCriteria
    {
        $criterion->update(['is_active' => ! $criterion->is_active]);

        return $criterion->fresh(['criterionType']);
    }

    public function reorder(int $departmentId, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            EvaluationCriteria::query()
                ->where('id', $id)
                ->where('department_id', $departmentId)
                ->update(['sort_order' => $index]);
        }
    }
}
