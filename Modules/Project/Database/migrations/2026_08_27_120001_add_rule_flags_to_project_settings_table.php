<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bổ sung cờ quy tắc dự án + chuyển mặc định mã DA_{date,"m/Y"}_{count}
 * cho bản cài đặt cũ còn đang dùng PRJ{count:4} / bộ đếm 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        $toAdd = [];
        if (! Schema::hasColumn('project_settings', 'shift_task_dates_with_project')) {
            $toAdd[] = 'shift_task_dates_with_project';
        }
        if (! Schema::hasColumn('project_settings', 'hide_cross_tasks_from_assignees')) {
            $toAdd[] = 'hide_cross_tasks_from_assignees';
        }
        if (! Schema::hasColumn('project_settings', 'hide_child_tasks_from_followers')) {
            $toAdd[] = 'hide_child_tasks_from_followers';
        }
        if (! Schema::hasColumn('project_settings', 'constrain_task_dates_to_project')) {
            $toAdd[] = 'constrain_task_dates_to_project';
        }

        if ($toAdd !== []) {
            Schema::table('project_settings', function (Blueprint $table) use ($toAdd) {
                foreach ($toAdd as $column) {
                    $table->boolean($column)->default(false);
                }
            });
        }

        DB::table('project_settings')
            ->where('code_pattern', 'PRJ{count:4}')
            ->where('code_counter', 0)
            ->update([
                'code_pattern' => 'DA_{date,"m/Y"}_{count}',
                'code_counter' => 344,
            ]);
    }

    public function down(): void
    {
        Schema::table('project_settings', function (Blueprint $table) {
            $drops = array_values(array_filter([
                Schema::hasColumn('project_settings', 'shift_task_dates_with_project') ? 'shift_task_dates_with_project' : null,
                Schema::hasColumn('project_settings', 'hide_cross_tasks_from_assignees') ? 'hide_cross_tasks_from_assignees' : null,
                Schema::hasColumn('project_settings', 'hide_child_tasks_from_followers') ? 'hide_child_tasks_from_followers' : null,
                Schema::hasColumn('project_settings', 'constrain_task_dates_to_project') ? 'constrain_task_dates_to_project' : null,
            ]));

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
