<?php

namespace Modules\Identity\App\Services;

use App\Models\User;
use Modules\Identity\App\Repositories\Contracts\RoleRepositoryInterface;
use Modules\Identity\Database\Seeders\RoleSeeder;

/**
 * Gán role member mặc định cho user chưa có vai trò nào — SSO/Google lần
 * đầu, hoặc user cũ trước khi có bước này. Idempotent (GET /api/me, login).
 */
class DefaultMemberRoleBootstrap
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
    ) {}

    public function ensureForUser(User $user): void
    {
        if ($user->roles()->exists()) {
            return;
        }

        $this->ensureSystemRolesExist();

        $role = $this->roles->findByCode('member');
        if ($role === null) {
            return;
        }

        $user->roles()->attach($role->id);
        $user->unsetRelation('roles');
    }

    private function ensureSystemRolesExist(): void
    {
        if ($this->roles->all()->isNotEmpty()) {
            return;
        }

        app(RoleSeeder::class)->run();
    }
}
