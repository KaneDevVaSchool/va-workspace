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

    public function customLabels(): array
    {
        return GlobalMenuVisibility::query()
            ->whereNotNull('custom_label')
            ->where('custom_label', '!=', '')
            ->pluck('custom_label', 'menu_key')
            ->all();
    }

    public function sortOrders(): array
    {
        // Chỉ item đã kéo-thả (có section_key). Toggle/đổi tên cũng tạo row
        // với sort_order mặc định 0 — nếu trả 0 thì AppSidebar đẩy mục đó
        // lên đầu, lệch với trang cấu hình.
        return GlobalMenuVisibility::query()
            ->whereNotNull('sort_order')
            ->whereNotNull('section_key')
            ->where('section_key', '!=', '')
            ->pluck('sort_order', 'menu_key')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    public function itemSections(): array
    {
        return GlobalMenuVisibility::query()
            ->whereNotNull('section_key')
            ->where('section_key', '!=', '')
            ->pluck('section_key', 'menu_key')
            ->all();
    }

    public function setCustomLabel(string $menuKey, ?string $label, ?int $updatedBy): GlobalMenuVisibility
    {
        return GlobalMenuVisibility::query()->updateOrCreate(
            ['menu_key' => $menuKey],
            ['custom_label' => ($label !== null && $label !== '') ? $label : null, 'updated_by' => $updatedBy],
        );
    }

    public function bulkUpdateLayout(array $items, ?int $updatedBy): void
    {
        foreach ($items as $item) {
            GlobalMenuVisibility::query()->updateOrCreate(
                ['menu_key' => $item['menu_key']],
                [
                    'sort_order' => (int) $item['sort_order'],
                    'section_key' => $item['section'] !== '' ? $item['section'] : null,
                    'updated_by' => $updatedBy,
                ],
            );
        }
    }
}
