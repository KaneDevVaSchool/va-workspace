<?php

namespace Modules\Identity\App\Repositories;

use Modules\Identity\App\Models\PermissionGrant;
use Modules\Identity\App\Repositories\Contracts\PermissionGrantRepositoryInterface;

class PermissionGrantRepository implements PermissionGrantRepositoryInterface
{
    /** @var array<string, array<string, bool>> In-memory cache per request */
    private array $cache = [];

    public function getGrantsForRole(
        string $roleCode,
        string $scopeType = 'global',
        ?int $scopeId = null,
    ): array {
        $cacheKey = "{$roleCode}|{$scopeType}|{$scopeId}";

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $rows = PermissionGrant::query()
            ->where('role_code', $roleCode)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->get(['permission_key', 'granted']);

        $map = [];
        foreach ($rows as $row) {
            $map[$row->permission_key] = (bool) $row->granted;
        }

        return $this->cache[$cacheKey] = $map;
    }

    public function upsert(
        string $roleCode,
        string $permissionKey,
        bool $granted,
        string $scopeType = 'global',
        ?int $scopeId = null,
        ?int $createdBy = null,
    ): void {
        PermissionGrant::query()->updateOrCreate(
            [
                'role_code' => $roleCode,
                'permission_key' => $permissionKey,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
            ],
            [
                'granted' => $granted,
                'created_by' => $createdBy,
            ],
        );

        $this->cache = [];
    }

    public function remove(
        string $roleCode,
        string $permissionKey,
        string $scopeType = 'global',
        ?int $scopeId = null,
    ): void {
        PermissionGrant::query()
            ->where('role_code', $roleCode)
            ->where('permission_key', $permissionKey)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->delete();

        $this->cache = [];
    }
}
