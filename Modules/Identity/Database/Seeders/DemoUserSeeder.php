<?php

namespace Modules\Identity\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Identity\App\Models\Department;

/**
 * User giả lập (KHÔNG có email thật cụ thể) — chỉ để phát triển/test UI
 * trước khi có API HRM. XÓA seeder này khi tích hợp API HRM thật.
 *
 * Các user này KHÔNG đăng nhập được qua Google (email @example.com không
 * nằm trong GOOGLE_ALLOWED_DOMAINS) — dùng để test list/UI cần dữ liệu
 * user + department có sẵn, không phải để test luồng SSO.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $departmentIds = Department::query()->pluck('id');

        if ($departmentIds->isEmpty()) {
            return;
        }

        User::factory()
            ->count(8)
            ->state(fn () => [
                'department_id' => $departmentIds->random(),
                'status' => 'active',
            ])
            ->create();
    }
}
