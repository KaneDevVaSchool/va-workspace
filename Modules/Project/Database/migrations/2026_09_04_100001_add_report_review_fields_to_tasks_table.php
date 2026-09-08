<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thay `auto_complete_on_report` (boolean) bằng `report_complete_action`
 * (3 lựa chọn: none/completed/under_review) — nút "Báo cáo hoàn thành"
 * (assignee-only, TaskService::reportComplete()) đọc field này để quyết
 * định chuyển task sang 'completed' hay 'under_review' (trạng thái mới,
 * xem TaskEnums::STATUSES).
 *
 * `failed_review_count` — derived field, KHÔNG fillable qua form thường,
 * chỉ tăng trong TaskScoreService::upsert() khi lưu đánh giá "Không đạt"
 * (is_passed = false), cùng pattern accepted_by/accepted_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('tasks', 'report_complete_action')) {
                $table->string('report_complete_action', 20)
                    ->default('none')
                    ->after('allow_child_people_view_parent');
            }
            if (! Schema::hasColumn('tasks', 'failed_review_count')) {
                $table->unsignedInteger('failed_review_count')->default(0)->after('report_complete_action');
            }
        });

        if (Schema::hasColumn('tasks', 'auto_complete_on_report')) {
            DB::table('tasks')->where('auto_complete_on_report', true)
                ->update(['report_complete_action' => 'completed']);

            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn('auto_complete_on_report');
            });
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('auto_complete_on_report')->default(false)->after('allow_child_people_view_parent');
        });

        DB::table('tasks')->where('report_complete_action', 'completed')
            ->update(['auto_complete_on_report' => true]);

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['report_complete_action', 'failed_review_count']);
        });
    }
};
