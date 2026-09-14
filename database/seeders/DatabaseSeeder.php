<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Credential\Database\Seeders\CredentialExternalAccountsSeeder;
use Modules\Credential\Database\Seeders\CredentialInternalToolsSeeder;
use Modules\Credential\Database\Seeders\CredentialProviderSeeder;
use Modules\Credential\Database\Seeders\CredentialSeeder;
use Modules\Evaluation\Database\Seeders\EvaluationCriteriaSeeder;
use Modules\Evaluation\Database\Seeders\EvaluationPositionSeeder;
use Modules\Evaluation\Database\Seeders\HrNsEvaluationCriteriaSeeder;
use Modules\Identity\Database\Seeders\CnttSoftwareTeamSeeder;
use Modules\Identity\Database\Seeders\DemoUserSeeder;
use Modules\Identity\Database\Seeders\DepartmentSeeder;
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
            // Nhân sự THẬT phòng CNTT (ban lãnh đạo + team Phần mềm/Phần
            // cứng) — PHẢI chạy sau SuperAdminSeeder (giữ nguyên department
            // CNTT mặc định của khoana@..., không còn bị NS ghi đè).
            CnttSoftwareTeamSeeder::class,
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
            // CnttSoftwareTeamSeeder (cần nhân sự NS/CNTT đã seed).
            HrOpportunityDemoSeeder::class,
            // Modules/Credential — danh mục nhà cung cấp trước, rồi tài
            // khoản mẫu "Claude Pro" (PHẢI chạy sau CredentialProviderSeeder
            // vì cần provider_id, và sau SuperAdminSeeder để gắn creator/viewer).
            CredentialProviderSeeder::class,
            CredentialSeeder::class,
            // 22 tài khoản thật của công ty (phần mềm/dịch vụ bên ngoài +
            // công cụ nội bộ VA Schools) — PHẢI chạy sau
            // CredentialProviderSeeder (cần provider_id).
            CredentialExternalAccountsSeeder::class,
            CredentialInternalToolsSeeder::class,
        ]);
    }
}
