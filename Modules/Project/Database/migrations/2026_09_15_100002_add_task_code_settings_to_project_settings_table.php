<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mã công việc (tasks.code) trước đây không được tự sinh khi tạo mới —
 * luôn để trống. Thêm mẫu mã + bộ đếm riêng cho công việc, cùng cú pháp
 * với code_pattern/code_counter của dự án (xem
 * 2026_08_27_100008_create_project_settings_table.php), nhưng đếm riêng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_settings', function (Blueprint $table) {
            $table->string('task_code_pattern')->default('CV_{date,"m/Y"}_{count}')->after('code_counter');
            $table->unsignedInteger('task_code_counter')->default(1)->after('task_code_pattern');
        });
    }

    public function down(): void
    {
        Schema::table('project_settings', function (Blueprint $table) {
            $table->dropColumn(['task_code_pattern', 'task_code_counter']);
        });
    }
};
