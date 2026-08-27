<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phạm vi triển khai của 1 dự án — nhiều dòng cho 1 project, mỗi dòng 1
 * phạm vi (Hội Sở/HT/KV/Phòng Ban) kèm % tỷ trọng KPI. `department_id`
 * chỉ có giá trị khi `scope_type = department`. Tổng weight_percent các
 * dòng KHÔNG bắt buộc phải bằng 100 (validate ở FormRequest: mỗi dòng
 * 0..100, Service không chặn cứng tổng).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects', 'id', 'project_scopes_project_fk')
                ->cascadeOnDelete();
            $table->string('scope_type', 30);
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments', 'id', 'project_scopes_department_fk')
                ->nullOnDelete();
            $table->decimal('weight_percent', 5, 2)->default(0);
            $table->timestamps();

            $table->index(['project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_scopes');
    }
};
