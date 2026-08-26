<?php

namespace Modules\Identity\App\Repositories;

use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Identity\App\Models\DepartmentSidebarConfig;
use Modules\Identity\App\Repositories\Contracts\DepartmentSidebarConfigRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent trực tiếp cho DepartmentSidebarConfig.
 */
class DepartmentSidebarConfigRepository implements DepartmentSidebarConfigRepositoryInterface
{
    public const SECTION_MENU_PREFIX = 'section:';

    public function hiddenKeysForDepartment(int $departmentId): array
    {
        return DepartmentSidebarConfig::query()
            ->where('department_id', $departmentId)
            ->where('is_visible', false)
            ->where('menu_key', 'not like', self::SECTION_MENU_PREFIX.'%')
            ->pluck('menu_key')
            ->values()
            ->all();
    }

    public function customLabelsForDepartment(int $departmentId): array
    {
        try {
            if (! Schema::hasColumn((new DepartmentSidebarConfig)->getTable(), 'custom_label')) {
                return [];
            }

            return DepartmentSidebarConfig::query()
                ->where('department_id', $departmentId)
                ->where('menu_key', 'not like', self::SECTION_MENU_PREFIX.'%')
                ->whereNotNull('custom_label')
                ->where('custom_label', '!=', '')
                ->pluck('custom_label', 'menu_key')
                ->all();
        } catch (QueryException) {
            return [];
        }
    }

    public function sortOrdersForDepartment(int $departmentId): array
    {
        try {
            if (! Schema::hasColumn((new DepartmentSidebarConfig)->getTable(), 'sort_order')) {
                return [];
            }

            return DepartmentSidebarConfig::query()
                ->where('department_id', $departmentId)
                ->where('menu_key', 'not like', self::SECTION_MENU_PREFIX.'%')
                ->whereNotNull('sort_order')
                ->pluck('sort_order', 'menu_key')
                ->map(fn ($value) => (int) $value)
                ->all();
        } catch (QueryException) {
            return [];
        }
    }

    public function itemSectionsForDepartment(int $departmentId): array
    {
        try {
            if (! Schema::hasColumn((new DepartmentSidebarConfig)->getTable(), 'section_key')) {
                return [];
            }

            return DepartmentSidebarConfig::query()
                ->where('department_id', $departmentId)
                ->where('menu_key', 'not like', self::SECTION_MENU_PREFIX.'%')
                ->whereNotNull('section_key')
                ->where('section_key', '!=', '')
                ->pluck('section_key', 'menu_key')
                ->all();
        } catch (QueryException) {
            return [];
        }
    }

    public function sectionLabelsForDepartment(int $departmentId): array
    {
        try {
            if (! Schema::hasColumn((new DepartmentSidebarConfig)->getTable(), 'custom_label')) {
                return [];
            }

            return DepartmentSidebarConfig::query()
                ->where('department_id', $departmentId)
                ->where('menu_key', 'like', self::SECTION_MENU_PREFIX.'%')
                ->whereNotNull('custom_label')
                ->where('custom_label', '!=', '')
                ->get()
                ->mapWithKeys(function (DepartmentSidebarConfig $row) {
                    $sectionKey = substr($row->menu_key, strlen(self::SECTION_MENU_PREFIX));

                    return $sectionKey === '' ? [] : [$sectionKey => $row->custom_label];
                })
                ->all();
        } catch (QueryException) {
            return [];
        }
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
        bool $updateLayout = false,
        ?int $sortOrder = null,
        ?string $sectionKey = null,
    ): DepartmentSidebarConfig {
        $values = [
            'is_visible' => $isVisible,
            'updated_by' => $updatedBy,
        ];

        if ($updateLabel) {
            $values['custom_label'] = $customLabel;
        }

        if ($updateLayout) {
            $values['sort_order'] = $sortOrder;
            $values['section_key'] = $sectionKey;
        }

        return DepartmentSidebarConfig::query()->updateOrCreate(
            ['department_id' => $departmentId, 'menu_key' => $menuKey],
            $values,
        );
    }
}
