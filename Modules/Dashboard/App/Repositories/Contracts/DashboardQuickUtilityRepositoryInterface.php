<?php

namespace Modules\Dashboard\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Dashboard\App\Models\DashboardQuickUtility;

interface DashboardQuickUtilityRepositoryInterface
{
    /** @return Collection<int, DashboardQuickUtility> */
    public function allOrdered(): Collection;

    /** @return Collection<int, DashboardQuickUtility> */
    public function visibleOrdered(): Collection;

    public function findByKey(string $utilityKey): ?DashboardQuickUtility;

    public function create(array $attributes): DashboardQuickUtility;

    public function update(DashboardQuickUtility $utility, array $attributes): DashboardQuickUtility;

    public function delete(DashboardQuickUtility $utility): void;

    /**
     * @param  array<int, array{utility_key: string, sort_order: int}>  $items
     */
    public function reorder(array $items): void;
}
