<?php

namespace Modules\Identity\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Identity\App\Models\Role;

/**
 * Seed 1 user super_admin sở hữu đủ 7 role hệ thống, phục vụ "xem thử"
 * (view-as) mọi vai trò — xem ViewAsService. Email cấu hình qua
 * SUPERADMIN_EMAIL (.env) / config('services.superadmin_email'), fallback
 * hard-code bên dưới nếu quên set env, để seeder luôn chạy được.
 *
 * User đăng nhập thật qua Google SSO (Modules/Identity) — 'name' ở đây chỉ
 * là placeholder, sẽ được GoogleAuthenticator ghi đè ở lần đăng nhập đầu.
 */
class SuperAdminSeeder extends Seeder
{
    private const FALLBACK_EMAIL = 'khoana@hcm.vaschools.edu.vn';

    public function run(): void
    {
        $email = config('services.superadmin_email') ?: self::FALLBACK_EMAIL;

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $user->name ?: 'Super Admin';
        $user->status = 'active';
        $user->save();

        $roleIds = Role::query()->pluck('id');
        $user->roles()->sync($roleIds);
    }
}
