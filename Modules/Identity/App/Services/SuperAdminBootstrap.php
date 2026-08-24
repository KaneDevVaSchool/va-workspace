<?php

namespace Modules\Identity\App\Services;

use App\Models\User;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;

/**
 * Gán đủ 7 role cho email SUPERADMIN_EMAIL — dùng khi seed, login Google,
 * và GET /api/me (idempotent). Tránh user SSO không có role → UI ẩn view-as.
 */
class SuperAdminBootstrap
{
    private const FALLBACK_EMAIL = 'khoana@hcm.vaschools.edu.vn';

    public function configuredEmail(): string
    {
        $email = config('services.superadmin_email') ?: self::FALLBACK_EMAIL;

        return strtolower(trim((string) $email));
    }

    public function ensureRolesForUser(User $user): void
    {
        if (strtolower((string) $user->email) !== $this->configuredEmail()) {
            return;
        }

        $this->ensureSystemRolesExist();

        $roleIds = Role::query()->pluck('id');
        if ($roleIds->isEmpty()) {
            return;
        }

        $user->roles()->sync($roleIds);
    }

    private function ensureSystemRolesExist(): void
    {
        if (Role::query()->exists()) {
            return;
        }

        app(RoleSeeder::class)->run();
    }
}
