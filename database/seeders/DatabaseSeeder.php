<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Identity\Database\Seeders\DemoUserSeeder;
use Modules\Identity\Database\Seeders\DepartmentSeeder;

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

        // Modules/Identity — TẠM THỜI giả lập User/Department, xem
        // Modules/Identity/module.json và README.md. Xoá 2 dòng dưới khi
        // tích hợp API HRM thật.
        $this->call([
            DepartmentSeeder::class,
            DemoUserSeeder::class,
        ]);
    }
}
