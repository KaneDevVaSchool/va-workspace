<?php

namespace Modules\Identity\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Identity\App\Models\DepartmentSidebarConfig;

/**
 * Contract cho tầng Repository — Service chỉ phụ thuộc interface này,
 * không phụ thuộc trực tiếp Eloquent.
 */
interface DepartmentSidebarConfigRepositoryInterface
{
    /** Danh sách menu_key đang bị ẩn (is_visible=false) của 1 phòng ban. */
    public function hiddenKeysForDepartment(int $departmentId): array;

    /**
     * Nhãn tuỳ chỉnh đang lưu (menu_key => custom_label), bỏ qua mục dùng tên mặc định.
     *
     * @return array<string, string>
     */
    public function customLabelsForDepartment(int $departmentId): array;

    /** Toàn bộ override hiện có của 1 phòng ban (kể cả is_visible=true nếu có row). */
    public function allByDepartment(int $departmentId): Collection;

    /**
     * Phòng ban nào đã lưu ít nhất một override menu — dùng cho cờ "đã có cấu hình".
     *
     * @param  list<int>  $departmentIds
     * @return list<int>
     */
    public function departmentIdsWithConfig(array $departmentIds): array;

    public function findByDepartmentAndKey(int $departmentId, string $menuKey): ?DepartmentSidebarConfig;

    public function upsert(
        int $departmentId,
        string $menuKey,
        bool $isVisible,
        ?int $updatedBy,
        bool $updateLabel = false,
        ?string $customLabel = null,
    ): DepartmentSidebarConfig;
}
