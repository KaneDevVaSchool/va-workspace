<?php

namespace Modules\Identity\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Identity\App\Models\GlobalMenuVisibility;
use Modules\Identity\App\Repositories\Contracts\GlobalMenuVisibilityRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent trực tiếp cho GlobalMenuVisibility.
 */
class GlobalMenuVisibilityRepository implements GlobalMenuVisibilityRepositoryInterface
{
    public function hiddenKeys(): array
    {
        return GlobalMenuVisibility::query()
            ->where('is_hidden', true)
            ->pluck('menu_key')
            ->values()
            ->all();
    }

    public function isHidden(string $menuKey): bool
    {
        return GlobalMenuVisibility::query()
            ->where('menu_key', $menuKey)
            ->where('is_hidden', true)
            ->exists();
    }

    public function findByKey(string $menuKey): ?GlobalMenuVisibility
    {
        return GlobalMenuVisibility::query()
            ->where('menu_key', $menuKey)
            ->first();
    }

    public function all(): Collection
    {
        return GlobalMenuVisibility::query()->get();
    }

    public function setHidden(string $menuKey, bool $isHidden, ?int $updatedBy): GlobalMenuVisibility
    {
        return GlobalMenuVisibility::query()->updateOrCreate(
            ['menu_key' => $menuKey],
            ['is_hidden' => $isHidden, 'updated_by' => $updatedBy],
        );
    }
}
