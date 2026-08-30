<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Khung chấm điểm theo phòng ban — chọn 1 trong 2 cách tính:
 *
 *   base_adjust   — điểm gốc (vd 100) ± theo số việc hoàn thành
 *                   và các tiêu chí cộng/trừ. Không tính khó/dễ việc.
 *                   Kết quả xếp theo thang phân loại của phòng.
 *   weighted_task — không điểm gốc; cộng theo số việc, mỗi việc nhân
 *                   trọng số khó/dễ và mức độ dự án.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_score_kits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')
                ->unique('eval_score_kits_dept_unique')
                ->constrained('departments', 'id', 'eval_score_kits_dept_fk')
                ->cascadeOnDelete();
            $table->string('mode', 32)->nullable();
            $table->decimal('base_score', 8, 2)->default(100);
            $table->decimal('points_per_completed_task', 8, 2)->default(0);
            $table->decimal('points_per_incomplete_task', 8, 2)->default(0);
            $table->boolean('use_project_importance')->default(true);
            $table->foreignId('classification_criterion_id')
                ->nullable()
                ->constrained('evaluation_criteria', 'id', 'eval_score_kits_class_fk')
                ->nullOnDelete();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users', 'id', 'eval_score_kits_creator_fk')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users', 'id', 'eval_score_kits_updater_fk')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_score_kits');
    }
};
