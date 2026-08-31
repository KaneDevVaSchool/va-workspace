<?php

namespace Modules\Identity\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Services\SuperAdminBootstrap;

/**
 * Chuyển user super_admin (SUPERADMIN_EMAIL, mặc định
 * khoana@hcm.vaschools.edu.vn) sang phòng Hành chính Nhân sự (NS).
 *
 * SuperAdminSeeder chỉ gán department_id khi user MỚI được tạo (department_id
 * null) — mặc định CNTT (xem SuperAdminSeeder). Seeder này ghi đè có chủ đích
 * để phục vụ luồng test "fit dữ liệu đánh giá theo bộ tiêu chí HCNS" — cần
 * chạy SAU SuperAdminSeeder trong DatabaseSeeder::run().
 *
 * Idempotent: dùng updateOrCreate qua email, không tạo trùng.
 */
class HrNsSuperAdminDepartmentSeeder extends Seeder
{
    private const TARGET_DEPARTMENT_CODE = 'NS';

    public function run(): void
    {
        $bootstrap = app(SuperAdminBootstrap::class);
        $email = $bootstrap->configuredEmail();

        $department = Department::query()->where('code', self::TARGET_DEPARTMENT_CODE)->first();
        if ($department === null) {
            $this->command?->warn('Không tìm thấy phòng ban code NS — bỏ qua HrNsSuperAdminDepartmentSeeder.');

            return;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($user === null) {
            $this->command?->warn(sprintf('Không tìm thấy user %s — bỏ qua HrNsSuperAdminDepartmentSeeder.', $email));

            return;
        }

        if ((int) $user->department_id !== (int) $department->id) {
            $user->department_id = $department->id;
            $user->save();
        }

        $this->command?->info(sprintf('Đã chuyển %s sang phòng %s.', $email, $department->name));
    }
}
