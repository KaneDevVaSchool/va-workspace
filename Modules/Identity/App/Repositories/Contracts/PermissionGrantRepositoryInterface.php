<?php

namespace Modules\Identity\App\Repositories\Contracts;

/**
 * Contract truy vấn DB override cho PermissionCatalog.
 * PermissionService chỉ phụ thuộc interface này — không gọi Eloquent trực tiếp.
 */
interface PermissionGrantRepositoryInterface
{
    /**
     * Lấy tất cả DB override cho 1 role theo exact scope.
     * Cache theo $cacheKey để tránh query lặp trong cùng 1 request.
     *
     * @return array<string, bool>  ['permission_key' => granted]
     */
    public function getGrantsForRole(
        string $roleCode,
        string $scopeType = 'global',
        ?int $scopeId = null,
    ): array;

    /**
     * Upsert 1 override — dùng bởi /superadmin/permissions CRUD.
     */
    public function upsert(
        string $roleCode,
        string $permissionKey,
        bool $granted,
        string $scopeType = 'global',
        ?int $scopeId = null,
        ?int $createdBy = null,
    ): void;

    /**
     * Xóa 1 override (quay về config default).
     */
    public function remove(
        string $roleCode,
        string $permissionKey,
        string $scopeType = 'global',
        ?int $scopeId = null,
    ): void;
}
