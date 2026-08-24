<?php

namespace Modules\Identity\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;

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

        $cntt = Department::query()->where('code', 'CNTT')->first();
        if ($cntt === null) {
            return;
        }

        $this->seedRoleDemoUser(
            email: 'pho-phong.cntt@example.com',
            name: 'Demo Phó phòng CNTT',
            roleCode: 'deputy_department_director',
            departmentId: $cntt->id,
        );

        $this->seedRoleDemoUser(
            email: 'truong-bo-phan.cntt@example.com',
            name: 'Demo Trưởng bộ phận CNTT',
            roleCode: 'section_head',
            departmentId: $cntt->id,
        );

        $ns = Department::query()->where('code', 'NS')->first();
        if ($ns !== null) {
            $this->seedRoleDemoUser(
                email: 'truong-phong.ns@example.com',
                name: 'Demo Trưởng phòng Hành chính Nhân sự',
                roleCode: 'department_director',
                departmentId: $ns->id,
            );
        }
    }

    private function seedRoleDemoUser(
        string $email,
        string $name,
        string $roleCode,
        int $departmentId,
    ): void {
        $role = Role::query()->where('code', $roleCode)->first();
        if ($role === null) {
            return;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->status = 'active';
        $user->department_id = $departmentId;
        $user->save();

        $user->roles()->sync([$role->id]);
    }
}
