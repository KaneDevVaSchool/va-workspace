<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Evaluation\Database\Seeders\EvaluationCriteriaSeeder;
use Modules\Evaluation\Database\Seeders\EvaluationPositionSeeder;
use Modules\Evaluation\Database\Seeders\HrNsEvaluationCriteriaSeeder;
use Modules\Identity\Database\Seeders\DemoUserSeeder;
use Modules\Identity\Database\Seeders\DepartmentSeeder;
use Modules\Identity\Database\Seeders\HrNsSuperAdminDepartmentSeeder;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Identity\Database\Seeders\SuperAdminSeeder;
use Modules\Project\Database\Seeders\HrOpportunityDemoSeeder;
use Modules\Project\Database\Seeders\ProjectSeeder;
use Modules\Project\Database\Seeders\TaskSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        // Modules/Identity — RoleSeeder/SuperAdminSeeder là dữ liệu hệ
        // thống (chạy mọi environment). DepartmentSeeder/DemoUserSeeder
        // TẠM THỜI giả lập User/Department, xem Modules/Identity/module.json
        // và README.md — xoá khi tích hợp API HRM thật.
        $this->call([
            RoleSeeder::class,
            DepartmentSeeder::class,
            SuperAdminSeeder::class,
            // Chuyển khoana@... (SUPERADMIN_EMAIL) sang phòng Hành chính
            // Nhân sự — PHẢI chạy sau SuperAdminSeeder (ghi đè department
            // mặc định CNTT của seeder đó).
            HrNsSuperAdminDepartmentSeeder::class,
            DemoUserSeeder::class,
            EvaluationCriteriaSeeder::class,
            EvaluationPositionSeeder::class,
            // Bộ tiêu chí đánh giá HCNS (A1–A13, B1–E2) — PHẢI chạy sau
            // DepartmentSeeder (cần phòng NS đã tồn tại).
            HrNsEvaluationCriteriaSeeder::class,
            ProjectSeeder::class,
            TaskSeeder::class,
            // 3 Project theo yêu cầu nghiệp vụ cụ thể (HRM, Cơ hội bất ngờ,
            // Quản lý Kho & Bán hàng) — PHẢI chạy sau DemoUserSeeder +
            // HrNsSuperAdminDepartmentSeeder (cần nhân sự NS/CNTT đã seed).
            HrOpportunityDemoSeeder::class,
        ]);
    }
}
