<?php

namespace Modules\DonTu\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seed demo cấu hình đơn từ: bộ trạng thái, mẫu biểu BM.01/BM.02, quy trình nghỉ việc.
 *
 * php artisan db:seed --class="Modules\DonTu\Database\Seeders\DonTuConfigDemoSeeder"
 */
class DonTuConfigDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DonTuStatusSetSeeder::class,
            DonTuFormTemplateSeeder::class,
            DonTuWorkflowSeeder::class,
        ]);
    }
}
