<?php

namespace Modules\Identity\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Repositories\Contracts\DepartmentRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent (Department) trực tiếp.
 * TẠM THỜI — sẽ bị thay bằng implementation gọi API HRM.
 */
class DepartmentRepository implements DepartmentRepositoryInterface
{
    public function allActive(): Collection
    {
        return Department::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function all(): Collection
    {
        return Department::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    public function allSyncedFromHrm(): Collection
    {
        return Department::query()
            ->whereNotNull('hrm_org_unit_uuid')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    public function allActiveSyncedFromHrmForPicker(): Collection
    {
        $rows = Department::query()
            ->whereNotNull('hrm_org_unit_uuid')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $this->dedupeHrmDepartmentsForPicker($rows);
    }

    /**
     * @param  Collection<int, Department>  $rows
     * @return Collection<int, Department>
     */
    private function dedupeHrmDepartmentsForPicker(Collection $rows): Collection
    {
        /** @var array<string, Department> $best */
        $best = [];

        foreach ($rows as $department) {
            $externalCode = strtolower(trim((string) ($department->external_code ?? '')));
            $key = $externalCode !== ''
                ? ($department->company_id ?? 0)."\0".$externalCode
                : 'uuid:'.$department->hrm_org_unit_uuid;

            if (! isset($best[$key]) || $department->id > $best[$key]->id) {
                $best[$key] = $department;
            }
        }

        return collect($best)
            ->sortBy(fn (Department $d): string => $d->name ?? '', SORT_NATURAL)
            ->values();
    }

    public function find(int $id): ?Department
    {
        return Department::query()->find($id);
    }

    public function findByHrmOrgUnitUuid(string $hrmOrgUnitUuid): ?Department
    {
        return Department::query()->where('hrm_org_unit_uuid', $hrmOrgUnitUuid)->first();
    }
}
