<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Danh sách key tab TUỲ CHỌN (discussion/report/attachments/
            // test_case/feedback) bị TẮT cho riêng dự án này — null/[] nghĩa
            // là tất cả đều bật (mặc định, không cần backfill dự án cũ).
            // Chi tiết/Công việc là cốt lõi, không nằm trong danh sách này.
            $table->json('disabled_tabs')->nullable()->after('constrain_task_dates_to_project');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('disabled_tabs');
        });
    }
};
