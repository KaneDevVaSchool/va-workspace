<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thứ tự + nhóm hiển thị của từng mục menu trái theo phòng ban.
 * null = dùng thứ tự / nhóm mặc định trong DepartmentSidebarConfigService.
 *
 * Tên nhóm (section title) lưu cùng bảng với menu_key dạng `section:{id}`
 * (ví dụ section:general) — chỉ dùng custom_label, không phải mục sidebar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('department_sidebar_configs', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->nullable()->after('custom_label');
            $table->string('section_key', 40)->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('department_sidebar_configs', function (Blueprint $table) {
            $table->dropColumn(['sort_order', 'section_key']);
        });
    }
};
