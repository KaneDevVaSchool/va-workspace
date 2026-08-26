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

    public function customLabelsForDepartment(int $departmentId): array
    {
        return DepartmentSidebarConfig::query()
            ->where('department_id', $departmentId)
            ->whereNotNull('custom_label')
            ->where('custom_label', '!=', '')
            ->pluck('custom_label', 'menu_key')
            ->all();
    }

    public function allByDepartment(int $departmentId): Collection
    {
        return DepartmentSidebarConfig::query()
            ->where('department_id', $departmentId)
            ->get();
    }

    public function departmentIdsWithConfig(array $departmentIds): array
    {
        if ($departmentIds === []) {
            return [];
        }

        return DepartmentSidebarConfig::query()
            ->whereIn('department_id', $departmentIds)
            ->distinct()
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function findByDepartmentAndKey(int $departmentId, string $menuKey): ?DepartmentSidebarConfig
    {
        return DepartmentSidebarConfig::query()
            ->where('department_id', $departmentId)
            ->where('menu_key', $menuKey)
            ->first();
    }

    public function upsert(
        int $departmentId,
        string $menuKey,
        bool $isVisible,
        ?int $updatedBy,
        bool $updateLabel = false,
        ?string $customLabel = null,
    ): DepartmentSidebarConfig {
        $values = [
            'is_visible' => $isVisible,
            'updated_by' => $updatedBy,
        ];

        if ($updateLabel) {
            $values['custom_label'] = $customLabel;
        }

        return DepartmentSidebarConfig::query()->updateOrCreate(
            ['department_id' => $departmentId, 'menu_key' => $menuKey],
            $values,
        );
    }
}
