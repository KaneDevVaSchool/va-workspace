<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Dashboard\App\Services\DashboardQuickUtilityService;

/**
 * Thêm lối tắt Trò chuyện vào catalog tiện ích PWA nhân viên.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dashboard_quick_utilities')) {
            return;
        }

        $defaults = DashboardQuickUtilityService::CATALOG['chat'] ?? null;
        if ($defaults === null) {
            return;
        }

        $exists = DB::table('dashboard_quick_utilities')
            ->where('utility_key', 'chat')
            ->exists();

        $now = now();
        if ($exists) {
            DB::table('dashboard_quick_utilities')
                ->where('utility_key', 'chat')
                ->update([
                    'label' => $defaults['label'],
                    'icon' => $defaults['icon'],
                    'tone' => $defaults['tone'],
                    'link_type' => $defaults['link_type'],
                    'link_target' => $defaults['link_target'],
                    'sort_order' => $defaults['sort_order'],
                    'updated_at' => $now,
                ]);

            return;
        }

        DB::table('dashboard_quick_utilities')->insert([
            'utility_key' => 'chat',
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

    public function down(): void
    {
        if (! Schema::hasTable('dashboard_quick_utilities')) {
            return;
        }

        DB::table('dashboard_quick_utilities')
            ->where('utility_key', 'chat')
            ->delete();
    }
};
