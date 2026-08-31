<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phiên bản cấu hình đánh giá của một phòng ban — bản chụp BẤT BIẾN tại thời
 * điểm chốt (publish), gồm cả khung chấm điểm và toàn bộ tiêu chí đang áp dụng.
 *
 * Vì sao gộp chung 1 bảng thay vì tách khung chấm điểm / tiêu chí:
 * EvaluationScoreKitService::present() vốn đã đọc khung chấm điểm KẾT HỢP các
 * tiêu chí nguồn (xếp loại / độ khó / tiến độ / chất lượng) — tách rời sẽ phải
 * đồng bộ 2 khoá ngoại độc lập, dễ lệch (khung mới nhưng tiêu chí cũ).
 * Báo cáo chỉ cần trỏ 1 khoá ngoại duy nhất để tính lại đúng điểm lịch sử.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_config_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')
                ->constrained('departments', 'id', 'eval_config_versions_dept_fk')
                ->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('status', 20)->default('active');
            $table->json('kit_snapshot')->nullable();
            $table->json('criteria_snapshot')->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('published_by')
                ->nullable()
                ->constrained('users', 'id', 'eval_config_versions_publisher_fk')
                ->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->date('effective_from')->nullable();
            $table->timestamps();

            $table->unique(['department_id', 'version_no'], 'eval_config_versions_dept_no_unique');
            $table->index(['department_id', 'status'], 'eval_config_versions_dept_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_config_versions');
    }
};
