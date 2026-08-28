<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhóm E — Giao việc theo giờ + thời gian dự kiến. Giữ nguyên start_date/
 * end_date kiểu date (zero breaking change cho whereDate()/format hiện có) —
 * start_time/due_time là giờ trong ngày, tuỳ chọn, ghép ở tầng hiển thị.
 * Thời gian thực hiện (tổng giờ thực tế) tính runtime từ task_worklogs.hours,
 * KHÔNG lưu cột riêng trên tasks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->decimal('estimated_hours', 6, 2)->nullable()->after('progress_percent'); // thời gian dự kiến, nhập tay
            $table->time('start_time')->nullable()->after('start_date'); // giờ trong ngày start_date
            $table->time('due_time')->nullable()->after('end_date'); // giờ hạn trong ngày end_date
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['due_time', 'start_time', 'estimated_hours']);
        });
    }
};
