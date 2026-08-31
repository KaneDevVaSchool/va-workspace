<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ghi nhận việc áp dụng một mức của tiêu chí hành vi cho một nhân sự.
 *
 * Tiêu chí hành vi (evaluation_criteria.type = 'behavior') chỉ là danh mục
 * "được cộng / bị trừ bao nhiêu điểm"; bảng này mới là dữ liệu thật: ai, mức
 * nào, ngày nào, vì lý do gì. Tên tiêu chí và điểm được chụp lại ngay lúc ghi
 * nhận để báo cáo cũ không đổi khi danh mục sửa về sau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')
                ->constrained('departments', 'id', 'eval_events_dept_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users', 'id', 'eval_events_user_fk')
                ->cascadeOnDelete();
            $table->foreignId('criterion_id')
                ->nullable()
                ->constrained('evaluation_criteria', 'id', 'eval_events_criterion_fk')
                ->nullOnDelete();
            $table->json('criterion_snapshot')->nullable();
            $table->string('level_code', 8)->nullable();
            $table->string('level_label', 80)->nullable();
            $table->decimal('score', 8, 2)->default(0);
            $table->date('occurred_at');
            $table->string('reason', 500)->nullable();
            $table->string('evidence_path', 255)->nullable();
            $table->foreignId('task_id')
                ->nullable()
                ->constrained('tasks', 'id', 'eval_events_task_fk')
                ->nullOnDelete();
            $table->string('status', 20)->default('approved');
            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users', 'id', 'eval_events_recorder_fk')
                ->nullOnDelete();
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users', 'id', 'eval_events_approver_fk')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('reject_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['department_id', 'user_id', 'occurred_at'], 'eval_events_dept_user_date_idx');
            $table->index('status', 'eval_events_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_events');
    }
};
