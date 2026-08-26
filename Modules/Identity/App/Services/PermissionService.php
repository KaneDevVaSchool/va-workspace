<?php

namespace Modules\Identity\App\Services;

use App\Models\User;
use Modules\Identity\App\Exceptions\PermissionKeyReserved;
use Modules\Identity\App\Exceptions\ScopeNotFound;
use Modules\Identity\App\Repositories\Contracts\PermissionGrantRepositoryInterface;
use Modules\Identity\App\Repositories\Contracts\TeamRepositoryInterface;

/**
 * RBAC engine — kiểm tra quyền granular theo pattern `module.action`.
 *
 * LUỒNG KIỂM TRA (trong allows()):
 *  1. super_admin thực sự (không phải view-as) → luôn true
 *  2. Lấy effective roles (có tính view-as) từ ViewAsService
 *  3. Với mỗi role, kiểm tra:
 *     a. DB override (PermissionGrantRepository) → nếu có → dùng ngay
 *     b. Config defaults (config/permissions.php) → hierarchy '*' → 'mod.*' → 'mod.action'
 *  4. Scope validation: nếu scopeType != global, kiểm tra user thuộc đúng scope
 *
 * SCOPE:
 *  global     → không ràng buộc scope tổ chức (check toàn hệ thống)
 *  department → user phải thuộc đúng phòng ban ($scopeId = department_id)
 *  team       → user phải thuộc đúng team ($scopeId = team_id)
 *
 * Dùng trong Controller/Middleware:
 *  app(PermissionService::class)->allows($user, 'task.delegate')
 *  app(PermissionService::class)->allows($user, 'project.create', 'department', $deptId)
 *
 * Hoặc qua User helper:
 *  $user->allows('task.delegate')
 *  $user->allowsScoped('project.create', 'department', $deptId)
 */
class PermissionService
{
    public function __construct(
        private readonly ViewAsService $viewAs,
        private readonly PermissionGrantRepositoryInterface $grants,
        private readonly TeamRepositoryInterface $teams,
    ) {}

    /**
     * Kiểm tra user có quyền $key không, tuỳ chọn trong phạm vi $scopeType/$scopeId.
     *
     * @param  string      $key        Permission key, vd. 'task.delegate', 'project.*'
     * @param  string      $scopeType  'global' | 'department' | 'team'
     * @param  int|null    $scopeId    department_id hoặc team_id (null = global)
     */
    public function allows(
        User $user,
        string $key,
        string $scopeType = 'global',
        ?int $scopeId = null,
    ): bool {
        // super_admin thực sự (không phải đang view-as role khác) → bypass hết
        if (! $this->viewAs->isImpersonating() && $user->isSuperAdmin()) {
            return true;
        }

        // Với view-as: dùng effective roles (role đang giả lập, không phải super_admin)
        $effectiveRoles = $this->viewAs->effectiveRoles($user);

        if (empty($effectiveRoles)) {
            return false;
        }

        // Kiểm tra scope trước — nếu scope không khớp, từ chối luôn
        if ($scopeType !== 'global' && $scopeId !== null) {
            if (! $this->userMatchesScope($user, $scopeType, $scopeId)) {
                return false;
            }
        }

        foreach ($effectiveRoles as $roleCode) {
            if ($this->roleAllows($roleCode, $key, $scopeType, $scopeId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kiểm tra 1 role có quyền $key không (DB override → config default).
     */
    public function roleAllows(
        string $roleCode,
        string $key,
        string $scopeType = 'global',
        ?int $scopeId = null,
    ): bool {
        // 1. Kiểm tra DB override (scope-specific trước, rồi global fallback)
        $dbResult = $this->checkDbOverride($roleCode, $key, $scopeType, $scopeId);
        if ($dbResult !== null) {
            return $dbResult;
        }

        // Nếu scope cụ thể không có override, thử fallback về global override
        if ($scopeType !== 'global') {
            $dbGlobalResult = $this->checkDbOverride($roleCode, $key, 'global', null);
            if ($dbGlobalResult !== null) {
                return $dbGlobalResult;
            }
        }

        // 2. Config defaults
        return $this->checkConfigDefault($roleCode, $key);
    }

    /**
     * Kiểm tra permission_key có phải reserved (chỉ super_admin) không.
     * UI superadmin/permissions dùng để ẩn/block các keys này.
     */
    public function isReserved(string $key): bool
    {
        $reserved = config('permissions.reserved', []);

        foreach ($reserved as $pattern) {
            if ($this->keyMatchesPattern($key, $pattern)) {
                // Ngoại lệ: một số reserved key được cấp cho role nhất định
                $exceptions = config('permissions.reserved_exceptions', []);
                if (isset($exceptions[$key])) {
                    return false;
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Danh sách permission keys hiệu lực của user (config default + DB override).
     * Dùng cho /api/me để frontend cache quyền (sidebar, route guard).
     *
     * Super admin thật → ['*']. Các role khác: duyệt catalog, gọi allows()
     * cùng scope global như middleware `permission:` để UI khớp API.
     *
     * @return array<string>
     */
    public function resolveGrantedKeys(User $user): array
    {
        if (! $this->viewAs->isImpersonating() && $user->isSuperAdmin()) {
            return ['*'];
        }

        $effectiveRoles = $this->viewAs->effectiveRoles($user);
        if ($effectiveRoles === []) {
            return [];
        }

        $granted = [];
        foreach (array_keys(config('permissions.catalog', [])) as $key) {
            if ($this->allows($user, $key)) {
                $granted[] = $key;
            }
        }

        return $granted;
    }

    /**
     * Ma trận chi tiết default/effective/override cho 1 scope — dùng cho
     * UI /superadmin/permissions. Giữ đúng precedence 2 bậc của roleAllows()
     * (scope cụ thể → global override → config default), KHÔNG mô phỏng
     * chuỗi 3 bậc team→department→global→config không tồn tại.
     *
     * @return array<string, array<string, array{
     *     default: bool, effective: bool, reserved: bool,
     *     global_override: bool|null, scoped_override: bool|null,
     *     effective_source: 'config'|'global'|'scoped',
     * }>>
     */
    public function matrixFor(string $scopeType, ?int $scopeId): array
    {
        $matrixConfig = config('permissions.matrix', []);
        $catalogKeys = array_keys(config('permissions.catalog', []));

        $result = [];

        foreach ($matrixConfig as $roleCode => $grantedPatterns) {
            if ($roleCode === 'super_admin') {
                continue; // super_admin luôn bypass, không có ý nghĩa trong ma trận
            }

            $globalGrants = $this->grants->getGrantsForRole($roleCode, 'global', null);
            $scopedGrants = $scopeType !== 'global'
                ? $this->grants->getGrantsForRole($roleCode, $scopeType, $scopeId)
                : [];

            $roleRow = [];

            foreach ($catalogKeys as $key) {
                $roleRow[$key] = $this->buildCell($roleCode, $key, $scopeType, $globalGrants, $scopedGrants);
            }

            $result[$roleCode] = $roleRow;
        }

        return $result;
    }

    /**
     * Tính 1 cell đơn lẻ (default/effective/override/source) — dùng sau khi
     * setGrant()/revokeGrant() để trả về ngay cho frontend patch tại chỗ,
     * không cần gọi lại matrixFor() cho toàn bộ ma trận. Cùng logic với
     * matrixFor(), chỉ khác đầu vào là 1 key thay vì cả catalog.
     */
    public function cellFor(string $roleCode, string $key, string $scopeType, ?int $scopeId): array
    {
        $globalGrants = $this->grants->getGrantsForRole($roleCode, 'global', null);
        $scopedGrants = $scopeType !== 'global'
            ? $this->grants->getGrantsForRole($roleCode, $scopeType, $scopeId)
            : [];

        return $this->buildCell($roleCode, $key, $scopeType, $globalGrants, $scopedGrants);
    }

    /**
     * @param  array<string, bool>  $globalGrants
     * @param  array<string, bool>  $scopedGrants
     * @return array{default: bool, effective: bool, reserved: bool, global_override: bool|null, scoped_override: bool|null, effective_source: string}
     */
    private function buildCell(string $roleCode, string $key, string $scopeType, array $globalGrants, array $scopedGrants): array
    {
        $default = $this->checkConfigDefault($roleCode, $key);
        $globalOverride = $this->lookupOverride($globalGrants, $key);
        $scopedOverride = $scopeType !== 'global'
            ? $this->lookupOverride($scopedGrants, $key)
            : null;

        if ($scopedOverride !== null) {
            $effective = $scopedOverride;
            $source = 'scoped';
        } elseif ($globalOverride !== null) {
            $effective = $globalOverride;
            $source = 'global';
        } else {
            $effective = $default;
            $source = 'config';
        }

        return [
            'default' => $default,
            'effective' => $effective,
            'reserved' => $this->isReserved($key),
            'global_override' => $globalOverride,
            'scoped_override' => $scopedOverride,
            'effective_source' => $source,
        ];
    }

    /**
     * Cấp/thu hồi 1 override — chặn reserved key trước khi ghi.
     *
     * @throws PermissionKeyReserved
     * @throws ScopeNotFound
     */
    public function setGrant(
        string $roleCode,
        string $key,
        bool $granted,
        string $scopeType,
        ?int $scopeId,
        int $createdBy,
    ): void {
        $this->guardGrantable($roleCode, $key, $scopeType, $scopeId);

        $this->grants->upsert($roleCode, $key, $granted, $scopeType, $scopeId, $createdBy);
    }

    /**
     * Xoá 1 override (quay về config default hoặc override ở scope khác).
     *
     * @throws PermissionKeyReserved
     * @throws ScopeNotFound
     */
    public function revokeGrant(
        string $roleCode,
        string $key,
        string $scopeType,
        ?int $scopeId,
    ): void {
        $this->guardGrantable($roleCode, $key, $scopeType, $scopeId);

        $this->grants->remove($roleCode, $key, $scopeType, $scopeId);
    }

    /**
     * @throws PermissionKeyReserved
     * @throws ScopeNotFound
     */
    private function guardGrantable(string $roleCode, string $key, string $scopeType, ?int $scopeId): void
    {
        if ($this->isReserved($key)) {
            $exceptions = config('permissions.reserved_exceptions', []);
            $allowedRoles = $exceptions[$key] ?? [];

            if (! in_array($roleCode, $allowedRoles, true)) {
                throw new PermissionKeyReserved($key);
            }
        }

        if ($scopeType === 'team' && $scopeId !== null && ! $this->teams->find($scopeId)) {
            throw new ScopeNotFound('team');
        }
    }

    /**
     * Tra override cho $key trong danh sách grants theo cùng hierarchy mà
     * checkDbOverride() dùng (exact → module.* → *).
     *
     * @param  array<string, bool>  $grants
     */
    private function lookupOverride(array $grants, string $key): ?bool
    {
        if (empty($grants)) {
            return null;
        }

        foreach ($this->buildHierarchyPatterns($key) as $pattern) {
            if (array_key_exists($pattern, $grants)) {
                return $grants[$pattern];
            }
        }

        return null;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Kiểm tra DB override — trả null nếu không có override cho combo này.
     *
     * @return bool|null  true/false nếu có override, null nếu không có
     */
    private function checkDbOverride(
        string $roleCode,
        string $key,
        string $scopeType,
        ?int $scopeId,
    ): ?bool {
        $dbGrants = $this->grants->getGrantsForRole($roleCode, $scopeType, $scopeId);

        if (empty($dbGrants)) {
            return null;
        }

        // Ưu tiên exact match trước, sau đó wildcard patterns
        $patterns = $this->buildHierarchyPatterns($key);
        foreach ($patterns as $pattern) {
            if (array_key_exists($pattern, $dbGrants)) {
                return $dbGrants[$pattern];
            }
        }

        return null;
    }

    /**
     * Kiểm tra config/permissions.php matrix cho role + key.
     */
    private function checkConfigDefault(string $roleCode, string $key): bool
    {
        $grantedPatterns = config("permissions.matrix.{$roleCode}", []);

        foreach ($grantedPatterns as $pattern) {
            if ($this->keyMatchesPattern($key, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kiểm tra user thuộc đúng scope (department hoặc team).
     * Trả true nếu scopeType không xác định (graceful fallback).
     */
    private function userMatchesScope(User $user, string $scopeType, int $scopeId): bool
    {
        return match ($scopeType) {
            'department' => (int) $user->department_id === $scopeId,
            'team' => isset($user->team_id) && (int) $user->team_id === $scopeId,
            default => true,
        };
    }

    /**
     * Kiểm tra $key có khớp $pattern không.
     * Patterns: '*', 'module.*', 'module.action'
     */
    private function keyMatchesPattern(string $key, string $pattern): bool
    {
        if ($pattern === '*') {
            return true;
        }

        if (str_ends_with($pattern, '.*')) {
            $prefix = substr($pattern, 0, -2);

            return $key === $prefix || str_starts_with($key, $prefix . '.');
        }

        return $key === $pattern;
    }

    /**
     * Tạo danh sách patterns theo hierarchy để tra cứu DB override.
     * Ví dụ: 'project.create' → ['project.create', 'project.*', '*']
     *
     * @return array<string>
     */
    private function buildHierarchyPatterns(string $key): array
    {
        $patterns = [$key];

        $parts = explode('.', $key);
        if (count($parts) >= 2) {
            $module = $parts[0];
            $patterns[] = $module . '.*';
        }

        $patterns[] = '*';

        return $patterns;
    }
}
