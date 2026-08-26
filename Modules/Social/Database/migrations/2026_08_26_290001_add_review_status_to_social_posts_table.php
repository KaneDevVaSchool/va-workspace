<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trạng thái duyệt bài viết — bài mới mặc định 'pending', chỉ hiện công khai
 * trên bảng tin khi 'approved'. Xem SocialPostService::create()/review().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->string('review_status', 20)->default('approved')->after('is_anonymous');
            $table->foreignId('reviewed_by')->nullable()->after('review_status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('review_reject_reason', 500)->nullable()->after('reviewed_at');

            $table->index('review_status');
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropIndex(['review_status']);
            $table->dropColumn(['review_status', 'reviewed_at', 'review_reject_reason']);
        });
    }
};
