<?php

namespace Modules\Identity\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Identity\App\Models\DepartmentSidebarConfig;
use Modules\Identity\App\Repositories\Contracts\DepartmentSidebarConfigRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent trực tiếp cho DepartmentSidebarConfig.
 */
class DepartmentSidebarConfigRepository implements DepartmentSidebarConfigRepositoryInterface
{
    public function hiddenKeysForDepartment(int $departmentId): array
    {
        return DepartmentSidebarConfig::query()
            ->where('department_id', $departmentId)
            ->where('is_visible', false)
            ->pluck('menu_key')
            ->values()
            ->all();
    }

    public function allByDepartment(int $departmentId): Collection
    {
        return DepartmentSidebarConfig::query()
            ->where('department_id', $departmentId)
            ->get();
    }

    public function findByDepartmentAndKey(int $departmentId, string $menuKey): ?DepartmentSidebarConfig
    {
        return DepartmentSidebarConfig::query()
            ->where('department_id', $departmentId)
            ->where('menu_key', $menuKey)
            ->first();
    }

    public function setVisibility(int $departmentId, string $menuKey, bool $isVisible, ?int $updatedBy): DepartmentSidebarConfig
    {
        return DepartmentSidebarConfig::query()->updateOrCreate(
            ['department_id' => $departmentId, 'menu_key' => $menuKey],
            ['is_visible' => $isVisible, 'updated_by' => $updatedBy],
        );
    }
}
