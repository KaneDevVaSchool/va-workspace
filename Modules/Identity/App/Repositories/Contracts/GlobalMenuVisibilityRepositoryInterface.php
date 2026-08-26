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
}
