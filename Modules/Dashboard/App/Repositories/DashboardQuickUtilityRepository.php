<?php

namespace Modules\Dashboard\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Dashboard\App\Models\DashboardQuickUtility;
use Modules\Dashboard\App\Repositories\Contracts\DashboardQuickUtilityRepositoryInterface;

class DashboardQuickUtilityRepository implements DashboardQuickUtilityRepositoryInterface
{
    public function allOrdered(): Collection
    {
        return DashboardQuickUtility::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function visibleOrdered(): Collection
    {
        return DashboardQuickUtility::query()
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function findByKey(string $utilityKey): ?DashboardQuickUtility
    {
        return DashboardQuickUtility::query()
            ->where('utility_key', $utilityKey)
            ->first();
    }

    public function create(array $attributes): DashboardQuickUtility
    {
        return DashboardQuickUtility::query()->create($attributes);
    }

    public function update(DashboardQuickUtility $utility, array $attributes): DashboardQuickUtility
    {
        $utility->fill($attributes);
        $utility->save();

        return $utility->fresh();
    }

    public function delete(DashboardQuickUtility $utility): void
    {
        $utility->delete();
    }

    public function reorder(array $items): void
    {
        foreach ($items as $item) {
            DashboardQuickUtility::query()
                ->where('utility_key', $item['utility_key'])
                ->update(['sort_order' => (int) $item['sort_order']]);
        }
    }
}
