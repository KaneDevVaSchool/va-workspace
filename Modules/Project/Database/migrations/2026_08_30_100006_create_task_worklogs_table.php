<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhóm E — Worklog chấm công giờ thực tế. Mỗi người chỉ tự ghi giờ của
 * chính mình (không chấm hộ) — user_id luôn = người đăng nhập, ép ở
 * TaskWorklogService, không cho client tự chọn user_id khác qua Request.
 * KHÔNG có rate_snapshot/chi phí — thuộc ProjectFinance (chưa dựng), xem
 * docs/VA_WORKSPACE_OVERVIEW.md §1 mục 6 + §15.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_worklogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')
                ->constrained('tasks', 'id', 'task_worklogs_task_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users', 'id', 'task_worklogs_user_fk')
                ->cascadeOnDelete(); // người thực hiện công việc trong worklog này — luôn = người ghi
            $table->date('work_date');
            $table->decimal('hours', 4, 2);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()
                ->constrained('users', 'id', 'task_worklogs_creator_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['task_id', 'work_date']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_worklogs');
    }
};
