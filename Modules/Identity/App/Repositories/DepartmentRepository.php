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
            ->with('company:id,code,name')
            ->whereNotNull('hrm_org_unit_uuid')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Danh mục HRM đã là 1 dòng / phòng ban — không gộp thêm.
        return $rows;
    }

    public function collapseDuplicatePickerIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $departments = Department::query()->whereIn('id', $ids)->get()->keyBy('id');
        /** @var array<string, int> $best */
        $best = [];

        foreach ($ids as $id) {
            $department = $departments->get($id);
            if ($department === null) {
                continue;
            }

            $key = $this->pickerDedupeKey($department);
            if (! isset($best[$key]) || $id > $best[$key]) {
                $best[$key] = $id;
            }
        }

        return array_values($best);
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
            $key = $this->pickerDedupeKey($department);

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

    private function pickerDedupeKey(Department $department): string
    {
        $externalCode = strtolower(trim((string) ($department->external_code ?? '')));

        return $externalCode !== ''
            ? ($department->company_id ?? 0)."\0".$externalCode
            : 'uuid:'.$department->hrm_org_unit_uuid;
    }
}
