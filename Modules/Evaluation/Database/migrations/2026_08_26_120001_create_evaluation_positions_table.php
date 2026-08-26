<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Vị trí đánh giá" (Evaluation Giai đoạn C, PR3) — danh mục CHỨC DANH dùng
 * chung toàn hệ thống (không thuộc riêng phòng ban), gán N-N vào
 * evaluation_templates để sau này Giai đoạn D tự chọn mẫu theo vị trí của
 * người được đánh giá.
 *
 * `hrm_position_uuid` chỉ là tham chiếu đối chiếu (nullable), KHÔNG phải
 * nguồn sự thật — cùng nguyên tắc `teams.hrm_team_uuid` (§8 overview),
 * chờ VA-HRM có API thật (xem docs/known-issues.md, memory
 * hrm-employee-sync-future). Danh mục tự quản trong Workspace ở giai đoạn
 * này.
 *
 * Xem docs/VA_WORKSPACE_OVERVIEW.md §7, §21 và plans/2026-08-26-mau-danh-gia.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_positions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('hrm_position_uuid')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('name');
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

    public function down(): void
    {
        Schema::dropIfExists('evaluation_template_positions');
        Schema::dropIfExists('evaluation_positions');
    }
};
