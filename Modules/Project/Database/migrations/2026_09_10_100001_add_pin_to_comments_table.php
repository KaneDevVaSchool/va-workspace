<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ghim bình luận (Thảo luận dự án) — chỉ áp dụng cho bình luận GỐC
 * (parent_comment_id null), khuôn theo
 * Modules\Social\Database\migrations\2026_08_25_150001_add_pin_scope_to_social_posts.php
 * nhưng không cần pin_scope (chỉ ghim trong phạm vi 1 dự án, không có khái
 * niệm "toàn công ty" như bài đăng mạng xã hội).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->boolean('is_pinned')->default(false)->after('content');
            $table->timestamp('pinned_at')->nullable()->after('is_pinned');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn(['is_pinned', 'pinned_at']);
        });
    }
};
