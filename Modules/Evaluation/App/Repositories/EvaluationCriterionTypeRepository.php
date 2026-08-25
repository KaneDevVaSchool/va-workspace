<?php

namespace Modules\Evaluation\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Evaluation\App\Models\EvaluationCriterionType;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationCriterionTypeRepositoryInterface;

class EvaluationCriterionTypeRepository implements EvaluationCriterionTypeRepositoryInterface
{
    public function allByDepartment(int $departmentId): Collection
    {
        return EvaluationCriterionType::query()
            ->where('department_id', $departmentId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function findByDepartment(int $id, int $departmentId): ?EvaluationCriterionType
    {
        return EvaluationCriterionType::query()
            ->where('id', $id)
            ->where('department_id', $departmentId)
            ->first();
    }

    public function codeExists(int $departmentId, string $code, ?int $ignoreId = null): bool
    {
        return EvaluationCriterionType::query()
            ->where('department_id', $departmentId)
            ->where('code', $code)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    public function codesForDepartment(int $departmentId): Collection
    {
        return EvaluationCriterionType::query()
            ->where('department_id', $departmentId)
            ->pluck('code');
    }

    public function create(array $data): EvaluationCriterionType
    {
        return EvaluationCriterionType::query()->create($data);
    }
}
