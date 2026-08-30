<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Vị trí đánh giá" — danh mục CHỨC DANH dùng chung toàn hệ thống (không
 * thuộc riêng phòng ban). Trước đây còn gán N-N vào evaluation_templates
 * (Mẫu đánh giá) — bảng pivot đó đã bị xoá cùng tính năng Mẫu đánh giá,
 * xem migration 2026_08_31_000002_drop_evaluation_templates_tables.php.
 *
 * `hrm_position_uuid` chỉ là tham chiếu đối chiếu (nullable), KHÔNG phải
 * nguồn sự thật — cùng nguyên tắc `teams.hrm_team_uuid` (§8 overview),
 * chờ VA-HRM có API thật (xem docs/known-issues.md, memory
 * hrm-employee-sync-future). Danh mục tự quản trong Workspace ở giai đoạn
 * này.
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
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_positions');
    }
};
