<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nâng cấp tạo dự án:
 *  - nhiều phòng ban thực hiện (pivot)
 *  - phòng ban phụ trách (lead_department_id)
 *  - cài đặt quyền theo từng dự án
 *  - phương pháp tính tiến độ mặc định ở cài đặt hệ thống
 *  - thang mức độ quan trọng 5 bậc
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_executing_departments')) {
            Schema::create('project_executing_departments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')
                    ->constrained('projects', 'id', 'proj_exec_depts_project_fk')
                    ->cascadeOnDelete();
                $table->foreignId('department_id')
                    ->constrained('departments', 'id', 'proj_exec_depts_department_fk')
                    ->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['project_id', 'department_id'], 'proj_exec_depts_unique');
            });
        }

        if (Schema::hasTable('projects') && Schema::hasTable('project_executing_departments')) {
            $now = now();
            $existing = DB::table('projects')
                ->whereNotNull('executing_department_id')
                ->get(['id', 'executing_department_id']);

            foreach ($existing as $row) {
                $exists = DB::table('project_executing_departments')
                    ->where('project_id', $row->id)
                    ->where('department_id', $row->executing_department_id)
                    ->exists();
                if ($exists) {
                    continue;
                }
                DB::table('project_executing_departments')->insert([
                    'project_id' => $row->id,
                    'department_id' => $row->executing_department_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                if (! Schema::hasColumn('projects', 'lead_department_id')) {
                    $table->foreignId('lead_department_id')
                        ->nullable()
                        ->after('lead_user_id')
                        ->constrained('departments', 'id', 'projects_lead_department_fk')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('projects', 'shift_task_dates_with_project')) {
                    $table->boolean('shift_task_dates_with_project')->default(false)->after('description');
                }
                if (! Schema::hasColumn('projects', 'hide_cross_tasks_from_assignees')) {
                    $table->boolean('hide_cross_tasks_from_assignees')->default(false);
                }
                if (! Schema::hasColumn('projects', 'hide_child_tasks_from_followers')) {
                    $table->boolean('hide_child_tasks_from_followers')->default(false);
                }
                if (! Schema::hasColumn('projects', 'constrain_task_dates_to_project')) {
                    $table->boolean('constrain_task_dates_to_project')->default(false);
                }
            });

            DB::table('projects')->where('importance', 'low')->update(['importance' => 'support']);
            DB::table('projects')->where('importance', 'medium')->update(['importance' => 'important']);
            DB::table('projects')->where('importance', 'high')->update(['importance' => 'high_priority']);
            DB::table('projects')->where('importance', 'critical')->update(['importance' => 'strategic']);
        }

        if (Schema::hasTable('project_settings') && ! Schema::hasColumn('project_settings', 'default_progress_method')) {
            Schema::table('project_settings', function (Blueprint $table) {
                $table->string('default_progress_method', 30)->default('average')->after('code_counter');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('projects')) {
            DB::table('projects')->where('importance', 'support')->update(['importance' => 'low']);
            DB::table('projects')->where('importance', 'assist')->update(['importance' => 'medium']);
            DB::table('projects')->where('importance', 'important')->update(['importance' => 'medium']);
            DB::table('projects')->where('importance', 'high_priority')->update(['importance' => 'high']);
            DB::table('projects')->where('importance', 'strategic')->update(['importance' => 'critical']);

            Schema::table('projects', function (Blueprint $table) {
                $drops = array_values(array_filter([
                    Schema::hasColumn('projects', 'shift_task_dates_with_project') ? 'shift_task_dates_with_project' : null,
                    Schema::hasColumn('projects', 'hide_cross_tasks_from_assignees') ? 'hide_cross_tasks_from_assignees' : null,
                    Schema::hasColumn('projects', 'hide_child_tasks_from_followers') ? 'hide_child_tasks_from_followers' : null,
                    Schema::hasColumn('projects', 'constrain_task_dates_to_project') ? 'constrain_task_dates_to_project' : null,
                ]));
                if ($drops !== []) {
                    $table->dropColumn($drops);
                }
                if (Schema::hasColumn('projects', 'lead_department_id')) {
                    $table->dropConstrainedForeignId('lead_department_id');
                }
            });
        }

        if (Schema::hasTable('project_settings') && Schema::hasColumn('project_settings', 'default_progress_method')) {
            Schema::table('project_settings', function (Blueprint $table) {
                $table->dropColumn('default_progress_method');
            });
        }

        Schema::dropIfExists('project_executing_departments');
    }
};
