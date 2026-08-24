<?php

namespace Modules\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\App\Models\Role;

/**
 * 9 role hệ thống — xem docs/VA_WORKSPACE_OVERVIEW.md §4.1 (+ phó phòng,
 * trưởng bộ phận). Dữ liệu hệ thống (không phải demo) nên chạy ở MỌI
 * environment, khác với DemoUserSeeder/DepartmentSeeder chỉ local/testing.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['code' => 'super_admin', 'name' => 'Super Admin', 'description' => 'God-mode, toàn quyền + độc quyền ma trận phân quyền'],
            ['code' => 'admin', 'name' => 'Admin', 'description' => 'Toàn quyền nghiệp vụ'],
            ['code' => 'director_officer', 'name' => 'Giám đốc điều hành', 'description' => 'Giao Hạng mục liên phòng ban, đánh giá mục tiêu quý/tháng/năm'],
            ['code' => 'department_director', 'name' => 'Trưởng phòng ban', 'description' => 'Quản lý phòng ban: task, phân công, hợp đồng, NCC, KB của PB mình'],
            ['code' => 'deputy_department_director', 'name' => 'Phó phòng', 'description' => 'Điều hành phòng ban khi trưởng phòng vắng; quyền nghiệp vụ gần trưởng phòng, cao hơn trưởng bộ phận'],
            ['code' => 'section_head', 'name' => 'Trưởng bộ phận', 'description' => 'Quản lý task & nhân sự trong một bộ phận thuộc phòng ban'],
            ['code' => 'team_lead', 'name' => 'Trưởng nhóm', 'description' => 'Quản lý task & thành viên trong 1 nhóm'],
            ['code' => 'member', 'name' => 'Nhân viên', 'description' => 'Làm việc, viết báo cáo ngày, tạo test case/đề xuất/KB của mình'],
            ['code' => 'viewer', 'name' => 'Người xem', 'description' => 'Chỉ xem (dashboard, hiệu suất, hợp đồng, dự án)'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(['code' => $role['code']], $role);
        }
    }
}
