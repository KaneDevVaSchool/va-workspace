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

        $departments = Department::query()->where('is_active', true)->get()->keyBy('code');

        if ($departments->isEmpty()) {
            return;
        }

        // Đồng nghiệp mẫu — idempotent theo email, phân đều các phòng ban
        // để UI (mention, thành viên, bảng tin) hiện tên phòng.
        foreach ([
            ['email' => 'hung.bgh@example.com', 'name' => 'Nguyễn Quốc Hùng', 'code' => 'BGH'],
            ['email' => 'hoa.bgh@example.com', 'name' => 'Lê Thị Hoa', 'code' => 'BGH'],
            ['email' => 'duc.cntt@example.com', 'name' => 'Phạm Minh Đức', 'code' => 'CNTT'],
            ['email' => 'linh.cntt@example.com', 'name' => 'Ngô Thị Linh', 'code' => 'CNTT'],
            ['email' => 'tuan.dt@example.com', 'name' => 'Vũ Anh Tuấn', 'code' => 'DT'],
            ['email' => 'mai.dt@example.com', 'name' => 'Đặng Thị Mai', 'code' => 'DT'],
            ['email' => 'son.ns@example.com', 'name' => 'Bùi Văn Sơn', 'code' => 'NS'],
            ['email' => 'thao.ns@example.com', 'name' => 'Hoàng Thị Thảo', 'code' => 'NS'],
            ['email' => 'phong.tc@example.com', 'name' => 'Trịnh Văn Phong', 'code' => 'TC'],
            ['email' => 'yen.tc@example.com', 'name' => 'Phan Thị Yến', 'code' => 'TC'],
        ] as $colleague) {
            $department = $departments->get($colleague['code']);
            if ($department === null) {
                continue;
            }

            $user = User::query()->firstOrNew(['email' => $colleague['email']]);
            $user->name = $colleague['name'];
            $user->status = 'active';
            $user->department_id = $department->id;
            $user->save();
        }

        $cntt = $departments->get('CNTT');
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

        $ns = $departments->get('NS');
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
