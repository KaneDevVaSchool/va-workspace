<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Team — thuộc 1 department cố định, sở hữu lâu dài của Workspace (KHÔNG
 * phải dữ liệu tạm chờ HRM như Department/User). `team_lead_id` luôn do
 * Workspace tự gán tay, không đọc/sync từ HRM (HRM có thể có "team lead"
 * riêng ngoài org-chart chính thức mà Workspace không phụ thuộc vào).
 *
 * `hrm_team_uuid` là tham chiếu đối chiếu (nullable) để đồng bộ về sau nếu
 * cần, KHÔNG phải nguồn sự thật.
 *
 * Xem plans/2026-08-24-quan-ly-phan-quyen-superadmin.md mục 3.0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('team_lead_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('hrm_team_uuid')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
