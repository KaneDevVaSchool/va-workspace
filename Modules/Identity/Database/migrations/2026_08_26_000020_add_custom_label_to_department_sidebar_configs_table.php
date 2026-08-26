<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cho phép phòng ban tự đặt tên hiển thị (custom_label) cho từng mục
 * menu trái. null = dùng nhãn mặc định trong DepartmentSidebarConfigService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('department_sidebar_configs', function (Blueprint $table) {
            $table->string('custom_label', 40)->nullable()->after('is_visible');
        });
    }

    public function down(): void
    {
        Schema::table('department_sidebar_configs', function (Blueprint $table) {
            $table->dropColumn('custom_label');
        });
    }
};
