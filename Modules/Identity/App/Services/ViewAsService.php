<?php

namespace Modules\Identity\App\Services;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Modules\Identity\App\Exceptions\NotSuperAdmin;
use Modules\Identity\App\Exceptions\RoleNotFound;
use Modules\Identity\App\Repositories\Contracts\RoleRepositoryInterface;

/**
 * "Xem thử" vai trò khác — chỉ super_admin được dùng. Không đổi user thật,
 * chỉ ghi đè role hiệu lực (effective role) vào session cho request hiện
 * tại. EnsureHasRole (app/Http/Middleware) đọc effectiveRoles() thay vì
 * $user->roles trực tiếp để override có tác dụng ở mọi route.
 */
class ViewAsService
{
    private const SESSION_KEY = 'impersonate.role_code';

    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly Session $session,
    ) {}

    /** @throws NotSuperAdmin|RoleNotFound */
    public function activate(User $user, string $roleCode): void
    {
        if (! $user->isSuperAdmin()) {
            throw new NotSuperAdmin();
        }

        if (! $this->roles->findByCode($roleCode)) {
            throw new RoleNotFound($roleCode);
        }

        $this->session->put(self::SESSION_KEY, $roleCode);
    }

    public function deactivate(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    public function isImpersonating(): bool
    {
        return $this->session->has(self::SESSION_KEY);
    }

    public function impersonatedRoleCode(): ?string
    {
        return $this->session->get(self::SESSION_KEY);
    }

    /**
     * Vai trò hiệu lực của user cho request hiện tại — nếu super_admin
     * đang "xem thử" 1 role, trả đúng role đó (và chỉ nó); ngược lại trả
     * toàn bộ role thật của user.
     *
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
