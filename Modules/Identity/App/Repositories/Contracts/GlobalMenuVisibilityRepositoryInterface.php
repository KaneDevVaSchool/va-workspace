<?php

namespace Modules\Identity\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Identity\App\Models\GlobalMenuVisibility;

/**
 * Contract cho tầng Repository — Service chỉ phụ thuộc interface này,
 * không phụ thuộc trực tiếp Eloquent.
 */
interface GlobalMenuVisibilityRepositoryInterface
{
    /** Danh sách menu_key đang bị ẩn toàn hệ thống (is_hidden=true). */
    public function hiddenKeys(): array;

    public function isHidden(string $menuKey): bool;

    public function findByKey(string $menuKey): ?GlobalMenuVisibility;

    /** Toàn bộ row hiện có (kể cả is_hidden=false nếu có row lịch sử). */
    public function all(): Collection;

    public function setHidden(string $menuKey, bool $isHidden, ?int $updatedBy): GlobalMenuVisibility;

    /**
     * Map menu_key => custom_label cho mọi item đã đổi tên.
     *
     * @return array<string, string>
     */
    public function customLabels(): array;

    /**
     * Map menu_key => sort_order cho mọi item đã có sort_order.
     *
     * @return array<string, int>
     */
    public function sortOrders(): array;

    /**
     * Map menu_key => section_key cho mọi item đã được chuyển nhóm.
     *
     * @return array<string, string>
     */
    public function itemSections(): array;

    public function setCustomLabel(string $menuKey, ?string $label, ?int $updatedBy): GlobalMenuVisibility;

    /**
     * Cập nhật hàng loạt sort_order + section_key cho nhiều item sau khi kéo thả.
     *
     * @param  array<array{menu_key: string, section: string, sort_order: int}>  $items
     */
    public function bulkUpdateLayout(array $items, ?int $updatedBy): void;
}
