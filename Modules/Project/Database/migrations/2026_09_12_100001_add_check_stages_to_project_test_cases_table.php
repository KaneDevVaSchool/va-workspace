<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_test_cases', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('project_test_cases', function (Blueprint $table) {
            // Nghiệm thu 2 lượt: Check lần 1 do assignee tự chấm, Check lần
            // 2 do người tạo testcase (created_by) chấm. Nếu check2 = failed,
            // ProjectTestCaseService::updateCheck2() tự reset check1 về
            // pending — xem docblock Service.
            $table->string('check1_status', 20)->default('pending')->after('actual_result');
            $table->foreignId('check1_by')->nullable()->after('check1_status')->constrained('users')->nullOnDelete();
            $table->timestamp('check1_at')->nullable()->after('check1_by');

            $table->string('check2_status', 20)->default('pending')->after('check1_at');
            $table->foreignId('check2_by')->nullable()->after('check2_status')->constrained('users')->nullOnDelete();
            $table->timestamp('check2_at')->nullable()->after('check2_by');

            $table->string('link_url', 2048)->nullable()->after('check2_at');
            $table->string('attachment_path')->nullable()->after('link_url');

            $table->index(['project_id', 'check1_status']);
            $table->index(['project_id', 'check2_status']);
        });
    }

    public function down(): void
    {
        Schema::table('project_test_cases', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'check1_status']);
            $table->dropIndex(['project_id', 'check2_status']);
            $table->dropConstrainedForeignId('check1_by');
            $table->dropConstrainedForeignId('check2_by');
            $table->dropColumn([
                'check1_status', 'check1_at',
                'check2_status', 'check2_at',
                'link_url', 'attachment_path',
            ]);
        });

        Schema::table('project_test_cases', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('actual_result');
        });
    }
};
