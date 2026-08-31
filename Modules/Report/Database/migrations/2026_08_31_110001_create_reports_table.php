<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cấu hình một báo cáo đã lưu của phòng ban.
 *
 * `evaluation_config_version_id` được chốt ngay lúc tạo và không đổi về sau —
 * đây là thứ giữ cho điểm của báo cáo cũ không chạy theo khung chấm điểm mới.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')
                ->constrained('departments', 'id', 'reports_dept_fk')
                ->cascadeOnDelete();
            $table->string('report_type', 40)->default('personnel_evaluation');
            $table->string('title', 150);
            $table->string('period_type', 20)->default('month');
            $table->date('period_from');
            $table->date('period_to');
            $table->foreignId('evaluation_config_version_id')
                ->nullable()
                ->constrained('evaluation_config_versions', 'id', 'reports_eval_version_fk')
                ->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users', 'id', 'reports_creator_fk')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users', 'id', 'reports_updater_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['department_id', 'report_type'], 'reports_dept_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
