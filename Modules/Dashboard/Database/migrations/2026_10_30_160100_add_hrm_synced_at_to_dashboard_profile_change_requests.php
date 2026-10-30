<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mốc đã đẩy yêu cầu sang hàng đợi HRM. Null = còn lưu tạm, sẽ gửi lại khi mở hồ sơ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_profile_change_requests', function (Blueprint $table) {
            $table->timestamp('hrm_synced_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_profile_change_requests', function (Blueprint $table) {
            $table->dropColumn('hrm_synced_at');
        });
    }
};
