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
            ->with(EvaluationCriteria::WITH_PRESENT)
            ->where('department_id', $departmentId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function idsByDepartment(int $departmentId): array
    {
        return EvaluationCriteria::query()
            ->where('department_id', $departmentId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function find(int $id): ?EvaluationCriteria
    {
        return EvaluationCriteria::query()->with(EvaluationCriteria::WITH_PRESENT)->find($id);
    }

    public function findByDepartment(int $id, int $departmentId): ?EvaluationCriteria
    {
        return EvaluationCriteria::query()
            ->with(EvaluationCriteria::WITH_PRESENT)
            ->where('id', $id)
            ->where('department_id', $departmentId)
            ->first();
    }

    public function create(array $data): EvaluationCriteria
    {
        $criterion = EvaluationCriteria::query()->create($data);
        $criterion->load(EvaluationCriteria::WITH_PRESENT);

        return $criterion;
    }

    public function update(EvaluationCriteria $criterion, array $data): EvaluationCriteria
    {
        $criterion->update($data);

        return $criterion->fresh(EvaluationCriteria::WITH_PRESENT);
    }

    public function delete(EvaluationCriteria $criterion): bool
    {
        return (bool) $criterion->delete();
    }

    public function toggleActive(EvaluationCriteria $criterion, ?int $updatedBy = null): EvaluationCriteria
    {
        $payload = ['is_active' => ! $criterion->is_active];
        if ($updatedBy !== null) {
            $payload['updated_by'] = $updatedBy;
        }
        $criterion->update($payload);

        return $criterion->fresh(EvaluationCriteria::WITH_PRESENT);
    }

    public function toggleUseInEvaluation(EvaluationCriteria $criterion, ?int $updatedBy = null): EvaluationCriteria
    {
        $payload = ['use_in_evaluation' => ! $criterion->use_in_evaluation];
        if ($updatedBy !== null) {
            $payload['updated_by'] = $updatedBy;
        }
        $criterion->update($payload);

        return $criterion->fresh(EvaluationCriteria::WITH_PRESENT);
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
