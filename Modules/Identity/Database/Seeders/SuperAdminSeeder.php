<?php

namespace Modules\Identity\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Services\SuperAdminBootstrap;

/**
 * Seed 1 user super_admin sở hữu đủ 7 role hệ thống, phục vụ "xem thử"
 * (view-as) mọi vai trò — xem ViewAsService. Email cấu hình qua
 * SUPERADMIN_EMAIL (.env) / config('services.superadmin_email'), fallback
 * hard-code bên dưới nếu quên set env, để seeder luôn chạy được.
 *
 * User đăng nhập thật qua Google SSO (Modules/Identity) — 'name' ở đây chỉ
 * là placeholder, sẽ được GoogleAuthenticator ghi đè ở lần đăng nhập đầu.
 *
 * department_id KHÔNG tự gán khi login Google (chưa có nguồn HRM — xem
 * GoogleAuthenticator), nên gán tay ở đây: user giả lập thuộc phòng CNTT
 * (Công nghệ thông tin) để test RBAC scope=department cho đúng ngữ cảnh.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $bootstrap = app(SuperAdminBootstrap::class);
        $email = $bootstrap->configuredEmail();

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($user === null) {
            $user = new User(['email' => $email]);
        }

        $user->name = $user->name ?: 'Super Admin';
        $user->status = 'active';

        if ($user->department_id === null) {
            $department = Department::query()->where('code', 'CNTT')->first();
            if ($department !== null) {
                $user->department_id = $department->id;
            }
        }

        $user->save();

        $bootstrap->ensureRolesForUser($user);
    }
}
