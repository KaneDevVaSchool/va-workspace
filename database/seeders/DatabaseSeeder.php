<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Identity\Database\Seeders\CnttSoftwareTeamSeeder;
use Modules\Identity\Database\Seeders\DemoUserSeeder;
use Modules\Identity\Database\Seeders\DepartmentSeeder;
use Modules\Identity\Database\Seeders\HcnsTeamSeeder;
use Modules\Identity\Database\Seeders\KinhDoanhTeamSeeder;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Identity\Database\Seeders\SuperAdminSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * CHỈ seed Role/Department/User/Team (quyền + nhân sự) — theo yêu cầu
     * thu hẹp phạm vi để seed production an toàn (không kèm dữ liệu mẫu).
     * Các seeder Evaluation/Project/Credential (bao gồm 22 tài khoản THẬT
     * của công ty ở CredentialExternalAccountsSeeder/CredentialInternalToolsSeeder)
     * vẫn còn file, dùng `db:seed --class=...` riêng khi cần — không gọi
     * tự động từ đây nữa để tránh seed nhầm dữ liệu mẫu/test lên production.
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
            // Nhân sự THẬT phòng Hành chính Nhân sự (HCNS).
            HcnsTeamSeeder::class,
            // Nhân sự THẬT phòng Kinh doanh — chờ API HRM để tự động mapping.
            KinhDoanhTeamSeeder::class,
        ]);
    }
}
