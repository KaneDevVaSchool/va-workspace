<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Identity\Database\Seeders\DemoUserSeeder;
use Modules\Identity\Database\Seeders\DepartmentSeeder;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Identity\Database\Seeders\SuperAdminSeeder;

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
            DemoUserSeeder::class,
        ]);
    }
}
