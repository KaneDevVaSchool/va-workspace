<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mẫu đánh giá (Evaluation Giai đoạn C) — gộp nhiều `evaluation_criteria`
 * thành 1 bộ có trọng số, dùng để tạo phiếu đánh giá nhân sự sau này
 * (Giai đoạn D, chưa dựng).
 *
 * Mặc định mẫu thuộc về 1 phòng ban (`department_id`), giống tiêu chí đánh
 * giá. Khi `is_global = true` (chỉ department_director trở lên được đặt,
 * quyền `evaluation.manage_global_template`), mẫu dùng chung cho TOÀN BỘ
 * hệ thống — `department_id` vẫn giữ nguyên phòng ban đã tạo ra mẫu (để
 * biết ai chịu trách nhiệm sửa/xoá), không set null.
 *
 * `code` tự sinh dạng EVT-0001, EVT-0002… tăng dần toàn hệ thống (không
 * theo từng phòng ban) — sinh trong EvaluationTemplateService::store()
 * bằng transaction + lock, xem plans/2026-08-26-mau-danh-gia.md.
 *
 * Xem docs/VA_WORKSPACE_OVERVIEW.md §7, §21 và plans/2026-08-26-mau-danh-gia.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_global')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_global', 'is_active']);
        });

        Schema::create('evaluation_template_criteria', function (Blueprint $table) {
            $table->id();

            // Tên constraint đặt tay ngắn gọn — tên auto-sinh của Laravel (bảng +
            // cột + "_foreign") vượt quá giới hạn 64 ký tự của MySQL khi cộng
            // thêm prefix va_workspace_.
            $table->foreignId('evaluation_template_id')
                ->constrained('evaluation_templates', 'id', 'eval_tpl_criteria_template_fk')
                ->cascadeOnDelete();
            $table->foreignId('evaluation_criteria_id')
                ->constrained('evaluation_criteria', 'id', 'eval_tpl_criteria_criterion_fk')
                ->cascadeOnDelete();

            /**
             * Trọng số hiển thị dạng chữ tiếng Việt phổ thông (CLAUDE.md §14) —
             * weight_value là số ẩn phía sau dùng để tính điểm, map 1-1 từ label.
             */
            $table->enum('weight_label', [
                'khong_quan_trong',
                'quan_trong',
                'kha_quan_trong',
                'rat_quan_trong',
            ])->default('quan_trong');
            $table->unsignedTinyInteger('weight_value')->default(2);

            $table->unsignedTinyInteger('required_score')->nullable();
            $table->boolean('count_in_total')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['evaluation_template_id', 'evaluation_criteria_id'], 'eval_tpl_criteria_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_template_criteria');
        Schema::dropIfExists('evaluation_templates');
    }
};
