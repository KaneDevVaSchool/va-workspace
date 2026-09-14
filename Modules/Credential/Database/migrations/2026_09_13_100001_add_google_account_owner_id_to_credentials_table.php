<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Google này thuộc về ai" đổi từ text tự do sang chọn 1 user thật trong
 * hệ thống. Giữ nguyên cột text `google_account_owner` cũ (không mất dữ
 * liệu lịch sử) — cột mới chỉ dùng khi có gắn, ưu tiên hiển thị tên user
 * qua quan hệ, fallback về text cũ nếu chưa gắn (xem
 * CredentialService::present()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credentials', function (Blueprint $table) {
            $table->foreignId('google_account_owner_id')->nullable()
                ->after('google_account_owner')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('credentials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('google_account_owner_id');
        });
    }
};
