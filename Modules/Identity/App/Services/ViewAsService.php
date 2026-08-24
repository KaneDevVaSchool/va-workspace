<?php

namespace Modules\Identity\App\Services;

use App\Models\User;
use Modules\Identity\App\Exceptions\NotSuperAdmin;
use Modules\Identity\App\Exceptions\RoleNotFound;
use Modules\Identity\App\Repositories\Contracts\RoleRepositoryInterface;

/**
 * "Xem thử" vai trò khác — chỉ super_admin được dùng. Override lưu session
 * request hiện tại (session() helper, không inject Session contract).
 */
class ViewAsService
{
    private const SESSION_KEY = 'impersonate.role_code';

    public function __construct(
        private readonly RoleRepositoryInterface $roles,
    ) {}

    public function displayActiveRole(User $user): ?string
    {
        if ($this->isImpersonating()) {
            return $this->impersonatedRoleCode();
        }

        if ($user->isSuperAdmin()) {
            return 'super_admin';
        }

        return $user->roles->pluck('code')->first();
    }

    /** @throws NotSuperAdmin|RoleNotFound */
    public function activate(User $user, string $roleCode): void
    {
        if (! $user->isSuperAdmin()) {
            throw new NotSuperAdmin();
        }

        if (! $this->roles->findByCode($roleCode)) {
            throw new RoleNotFound($roleCode);
        }

        if ($roleCode === 'super_admin') {
            session()->forget(self::SESSION_KEY);

            return;
        }

        session()->put(self::SESSION_KEY, $roleCode);
    }

    public function deactivate(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function isImpersonating(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    public function impersonatedRoleCode(): ?string
    {
        return session()->get(self::SESSION_KEY);
    }

    /**
     * @return array<int, string>
     */
    public function effectiveRoles(User $user): array
    {
        $override = $this->impersonatedRoleCode();

        if ($override !== null && $user->isSuperAdmin()) {
            return [$override];
        }

        return $user->roles->pluck('code')->all();
    }
}
