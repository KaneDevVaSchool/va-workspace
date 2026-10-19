<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tiện ích nhanh trên trang Dashboard của tôi (/dashboard/me) — super_admin
 * cấu hình toàn hệ thống (permission workspace_config.manage_dashboard_quick_utilities).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_quick_utilities', function (Blueprint $table) {
            $table->id();
            $table->string('utility_key', 64)->unique();
            $table->string('label', 120);
            $table->string('icon', 64)->default('layoutGrid');
            $table->string('tone', 24)->default('primary');
            $table->string('link_type', 16)->default('none'); // route | url | none
            $table->string('link_target', 512)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            ['utility_key' => 'attendance', 'label' => 'Chấm công', 'icon' => 'clock', 'tone' => 'tertiary', 'link_type' => 'route', 'link_target' => 'employee.attendance', 'sort_order' => 10],
            ['utility_key' => 'leave', 'label' => 'Nghỉ phép', 'icon' => 'calendar', 'tone' => 'gold', 'link_type' => 'route', 'link_target' => 'employee.leave', 'sort_order' => 20],
            ['utility_key' => 'contacts', 'label' => 'Danh bạ', 'icon' => 'users', 'tone' => 'primary', 'link_type' => 'route', 'link_target' => 'employee.contacts', 'sort_order' => 30],
            ['utility_key' => 'social', 'label' => 'Bảng tin', 'icon' => 'megaphone', 'tone' => 'tertiary', 'link_type' => 'route', 'link_target' => 'employee.feed', 'sort_order' => 40],
        ];

        foreach ($defaults as $row) {
            DB::table('dashboard_quick_utilities')->insert([
                ...$row,
                'is_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_quick_utilities');
    }
};
