<?php

namespace Modules\Evaluation\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Evaluation\App\Models\EvaluationConfigVersion;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationConfigVersionRepositoryInterface;

class EvaluationConfigVersionRepository implements EvaluationConfigVersionRepositoryInterface
{
    public function activeForDepartment(int $departmentId): ?EvaluationConfigVersion
    {
        return EvaluationConfigVersion::query()
            ->with('publisher')
            ->where('department_id', $departmentId)
            ->where('status', EvaluationConfigVersion::STATUS_ACTIVE)
            ->orderByDesc('version_no')
            ->first();
    }

    public function find(int $id): ?EvaluationConfigVersion
    {
        return EvaluationConfigVersion::query()
            ->with('publisher')
            ->find($id);
    }

    public function allByDepartment(int $departmentId): Collection
    {
        return EvaluationConfigVersion::query()
            ->with('publisher')
            ->where('department_id', $departmentId)
            ->orderByDesc('version_no')
            ->get();
    }

    public function maxVersionNo(int $departmentId): int
    {
        return (int) EvaluationConfigVersion::query()
            ->where('department_id', $departmentId)
            ->max('version_no');
    }

    public function supersedeActive(int $departmentId): void
    {
        EvaluationConfigVersion::query()
            ->where('department_id', $departmentId)
            ->where('status', EvaluationConfigVersion::STATUS_ACTIVE)
            ->update(['status' => EvaluationConfigVersion::STATUS_SUPERSEDED]);
    }

    public function create(array $data): EvaluationConfigVersion
    {
        $version = EvaluationConfigVersion::query()->create($data);

        return $version->fresh('publisher');
    }
}
