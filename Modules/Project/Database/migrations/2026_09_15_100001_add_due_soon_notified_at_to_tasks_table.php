<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Đánh dấu đã gửi thông báo "sắp quá hạn" — chống cron gửi lặp
            // mỗi ngày cho cùng 1 task. Reset về null khi end_date đổi
            // (TaskService::update()). Xem NotifyTasksDueSoonCommand.
            $table->timestamp('due_soon_notified_at')->nullable()->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('due_soon_notified_at');
        });
    }
};
