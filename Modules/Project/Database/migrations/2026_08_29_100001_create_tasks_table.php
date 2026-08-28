<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()
                ->constrained('tasks')->restrictOnDelete();

            $table->string('code', 20)->nullable();
            $table->string('type', 20)->default('task'); // task | phase | category
            $table->string('title');
            $table->text('description')->nullable();

            $table->string('status', 30)->default('not_started');
            $table->string('priority', 20)->nullable(); // low | medium | high | urgent

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();

            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedTinyInteger('progress_percent')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            // Chừa chỗ Task Delegation xuyên phòng ban (docs/VA_WORKSPACE_OVERVIEW.md §6) —
            // nullable, chưa xử lý logic uỷ quyền ở giai đoạn này.
            $table->foreignId('origin_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('delegated_to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('delegated_to_employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('delegation_status', 20)->nullable(); // pending|accepted|in_progress|done|rejected

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'parent_id']);
            $table->index(['project_id', 'status']);
            $table->index(['assignee_id']);
            $table->index(['project_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
