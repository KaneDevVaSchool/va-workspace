<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Dashboard\App\Services\DashboardQuickUtilityService;

/**
 * Chỉ giữ 4 lối tắt cố định: chấm công, nghỉ phép, danh bạ, bảng tin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dashboard_quick_utilities')) {
            return;
        }

        $keys = array_keys(DashboardQuickUtilityService::CATALOG);

        DB::table('dashboard_quick_utilities')
            ->whereNotIn('utility_key', $keys)
            ->delete();

        $now = now();
        foreach (DashboardQuickUtilityService::CATALOG as $utilityKey => $defaults) {
            $exists = DB::table('dashboard_quick_utilities')
                ->where('utility_key', $utilityKey)
                ->exists();

            if ($exists) {
                DB::table('dashboard_quick_utilities')
                    ->where('utility_key', $utilityKey)
                    ->update([
                        'link_type' => $defaults['link_type'],
                        'link_target' => $defaults['link_target'],
                        'sort_order' => $defaults['sort_order'],
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('dashboard_quick_utilities')->insert([
                    'utility_key' => $utilityKey,
                    'label' => $defaults['label'],
                    'icon' => $defaults['icon'],
                    'tone' => $defaults['tone'],
                    'link_type' => $defaults['link_type'],
                    'link_target' => $defaults['link_target'],
                    'sort_order' => $defaults['sort_order'],
                    'is_visible' => true,
                    'is_under_maintenance' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Không khôi phục các mục đã xoá.
    }
};
