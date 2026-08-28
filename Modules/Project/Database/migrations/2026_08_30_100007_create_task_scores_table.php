<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhóm G — Đánh giá tối thiểu. Đây là reference/phiếu chấm đơn giản, KHÔNG
 * phải TaskScoringConfig/Kpi đầy đủ (Phase 4, chưa dựng — xem
 * docs/VA_WORKSPACE_OVERVIEW.md §7). 1 task chỉ có 1 bản ghi điểm hiện hành
 * (unique task_id) — update ghi đè, không lưu lịch sử nhiều dòng.
 *
 * rating_result là text tự do (KHÔNG enum DB cứng) — kết quả đánh giá tuỳ
 * cấu hình evaluation tương lai, không mặc định là "Đạt/Không đạt".
 *
 * KHÔNG có schedule_compliance (hệ số tiến độ) — chờ đúng
 * TaskScoringConfig/phiếu đánh giá phòng ban tính từ tiêu chí thật.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')
                ->constrained('tasks', 'id', 'task_scores_task_fk')
                ->cascadeOnDelete();
            $table->decimal('rating_score', 5, 2)->nullable();
            $table->string('rating_result', 100)->nullable(); // text tự do, KHÔNG enum DB
            $table->text('rating_desc')->nullable();
            $table->foreignId('scored_by')->nullable()
                ->constrained('users', 'id', 'task_scores_scored_by_fk')
                ->nullOnDelete();
            $table->timestamp('scored_at')->nullable();
            $table->timestamps();

            $table->unique('task_id', 'task_scores_task_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_scores');
    }
};
