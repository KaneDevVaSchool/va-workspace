<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('constrain_child_dates')->default(false)->after('due_time');
            $table->boolean('hide_cross_tasks_from_assignees')->default(false)->after('constrain_child_dates');
            $table->boolean('hide_from_parent_assignees')->default(false)->after('hide_cross_tasks_from_assignees');
            $table->boolean('hide_from_parent_followers')->default(false)->after('hide_from_parent_assignees');
            $table->boolean('hide_child_tasks_from_followers')->default(false)->after('hide_from_parent_followers');
            $table->boolean('allow_child_people_view_parent')->default(false)->after('hide_child_tasks_from_followers');
            $table->boolean('auto_complete_on_report')->default(false)->after('allow_child_people_view_parent');
            $table->string('completed_interaction_policy', 20)->default('inherit')->after('auto_complete_on_report');
            $table->string('report_description_requirement', 24)->default('none')->after('completed_interaction_policy');
            $table->string('report_attachment_requirement', 24)->default('none')->after('report_description_requirement');
        });

        Schema::create('task_watchers', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['task_id', 'user_id']);
        });

        Schema::create('task_collaborators', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['task_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_collaborators');
        Schema::dropIfExists('task_watchers');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'constrain_child_dates',
                'hide_cross_tasks_from_assignees',
                'hide_from_parent_assignees',
                'hide_from_parent_followers',
                'hide_child_tasks_from_followers',
                'allow_child_people_view_parent',
                'auto_complete_on_report',
                'completed_interaction_policy',
                'report_description_requirement',
                'report_attachment_requirement',
            ]);
        });
    }
};
