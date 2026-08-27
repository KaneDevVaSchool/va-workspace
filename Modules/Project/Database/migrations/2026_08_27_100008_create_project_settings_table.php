<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cài đặt dự án dùng chung toàn hệ thống — CHỈ 1 dòng duy nhất áp dụng.
 * Repository tự đảm bảo luôn có đúng 1 dòng (firstOrCreate([], [...])).
 *
 * code_pattern: cú pháp mẫu mã, hỗ trợ {count} / {count:N} và
 * {date,"FORMAT"} (AMIS) hoặc {date:FORMAT} (legacy PHP date()).
 * code_counter: số sẽ dùng cho dự án tiếp theo, tăng sau mỗi lần sinh mã.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_settings', function (Blueprint $table) {
            $table->id();
            $table->string('code_pattern')->default('DA_{date,"m/Y"}_{count}');
            $table->unsignedInteger('code_counter')->default(344);
            $table->boolean('auto_start_on_begin_date')->default(false);
            $table->boolean('shift_task_dates_with_project')->default(false);
            $table->boolean('hide_cross_tasks_from_assignees')->default(false);
            $table->boolean('hide_child_tasks_from_followers')->default(false);
            $table->boolean('constrain_task_dates_to_project')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_settings');
    }
};
