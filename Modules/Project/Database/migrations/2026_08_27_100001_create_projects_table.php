<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dự án (Project module — giai đoạn 1: CRUD). Chuẩn bị chỗ trống hợp lý
 * cho Task/công việc con tính điểm/tiến độ tự động ở giai đoạn sau:
 * `progress_method` quyết định cách gộp % tiến độ từ Task, `evaluation_score`
 * để trống (null) — sẽ tổng hợp từ Task tương lai, KHÔNG tính gì ở đây.
 *
 * `code` tự sinh dạng PRJ0001, PRJ0002… tăng dần — sinh trong
 * ProjectRepository::nextCode() bằng transaction + lock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('type', 30);
            $table->string('name');
            $table->foreignId('lead_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('progress_method', 30)->default('average');
            $table->string('status', 30)->default('planning');
            $table->string('importance', 20)->default('medium');
            $table->text('description')->nullable();
            $table->string('avatar_path')->nullable();
            $table->decimal('evaluation_score', 5, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['type']);
            $table->index(['importance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
