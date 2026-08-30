<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Xoá tính năng "Mẫu đánh giá" (Evaluation Giai đoạn C) — bị bỏ khỏi phạm
 * vi dự án, chưa từng có phiếu đánh giá thật (Giai đoạn D) dùng đến.
 *
 * Drop theo đúng thứ tự phụ thuộc khoá ngoại: các bảng con/pivot trước,
 * `evaluation_templates` sau cùng. `evaluation_positions` (danh mục Vị trí
 * đánh giá) KHÔNG bị xoá — vẫn là danh mục dùng chung độc lập, chỉ mất đi
 * quan hệ N-N với mẫu đánh giá qua `evaluation_template_positions`.
 *
 * down() dựng lại đúng cấu trúc tại thời điểm xoá (đã qua các migration đổi
 * weight_percent/field_type text+bonus) — không phục hồi dữ liệu cũ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('evaluation_template_positions');
        Schema::dropIfExists('evaluation_template_custom_fields');
        Schema::dropIfExists('evaluation_template_criteria');
        Schema::dropIfExists('evaluation_templates');
    }

    public function down(): void
    {
        Schema::create('evaluation_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->cascadeOnDelete();
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
            $table->foreignId('evaluation_template_id')
                ->constrained('evaluation_templates', 'id', 'eval_tpl_criteria_template_fk')
                ->cascadeOnDelete();
            $table->foreignId('evaluation_criteria_id')
                ->constrained('evaluation_criteria', 'id', 'eval_tpl_criteria_criterion_fk')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('weight_percent')->default(10);
            $table->unsignedTinyInteger('required_score')->nullable();
            $table->boolean('count_in_total')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['evaluation_template_id', 'evaluation_criteria_id'], 'eval_tpl_criteria_unique');
        });

        Schema::create('evaluation_template_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_template_id')
                ->constrained('evaluation_templates', 'id', 'eval_tpl_custom_fields_template_fk')
                ->cascadeOnDelete();
            $table->string('label');
            $table->enum('field_type', ['text', 'bonus'])->default('text');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('evaluation_template_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_template_id')
                ->constrained('evaluation_templates', 'id', 'eval_tpl_positions_template_fk')
                ->cascadeOnDelete();
            $table->foreignId('evaluation_position_id')
                ->constrained('evaluation_positions', 'id', 'eval_tpl_positions_position_fk')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['evaluation_template_id', 'evaluation_position_id'],
                'eval_tpl_positions_unique',
            );
        });
    }
};
