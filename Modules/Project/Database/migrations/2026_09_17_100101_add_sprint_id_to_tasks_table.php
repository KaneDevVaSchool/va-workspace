<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Task (type=task) gán vào 1 sprint — quan hệ phẳng, KHÔNG qua
            // parent_id (khác phase, vốn nằm trong cây WBS qua parent_id).
            $table->foreignId('sprint_id')->nullable()->after('parent_id')->constrained('sprints')->nullOnDelete();
            $table->index(['project_id', 'sprint_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'sprint_id']);
            $table->dropConstrainedForeignId('sprint_id');
        });
    }
};
