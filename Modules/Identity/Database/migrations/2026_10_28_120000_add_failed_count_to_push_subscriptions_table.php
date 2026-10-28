<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đếm số lần push bị từ chối vì xác thực (401/403 — vd. đổi khoá VAPID) để tự xoá
 * subscription hỏng, thay vì giữ mãi và thử lại ở mọi thông báo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->unsignedTinyInteger('failed_count')->default(0)->after('content_encoding');
        });
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropColumn('failed_count');
        });
    }
};
