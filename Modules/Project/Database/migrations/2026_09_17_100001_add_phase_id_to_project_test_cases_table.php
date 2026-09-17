<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_test_cases', function (Blueprint $table) {
            // Testcase gắn vào 1 giai đoạn (tasks.type=phase) của cùng dự án
            // để tổng hợp theo module/tính năng — xem Task::TYPES,
            // ProjectTasksTab.vue groupProjectTasksByPhase() cho khái niệm
            // "phase" đã có sẵn trong cây WBS.
            $table->foreignId('phase_id')->nullable()->after('assignee_id')->constrained('tasks')->nullOnDelete();
            $table->index(['project_id', 'phase_id']);
        });
    }

    public function down(): void
    {
        Schema::table('project_test_cases', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'phase_id']);
            $table->dropConstrainedForeignId('phase_id');
        });
    }
};
