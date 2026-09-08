<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đánh dấu "đã đọc đến đâu" của từng user trên 1 luồng thảo luận
 * (commentable polymorphic — Task hoặc Project, cùng morph map
 * 'task'|'project' đã đăng ký ở ProjectServiceProvider::boot()). Dùng để
 * tính "còn bình luận chưa đọc của thành viên X" ở sidebar Thảo luận dự án.
 * Khuôn theo Modules\Social\Database\migrations\2026_08_25_260001_create_social_post_views_table.php
 * (1 dòng / user + object, unique) nhưng thêm cột last_read_at riêng thay
 * vì dùng updated_at, vì đây là mốc "đọc đến lúc nào" — ý nghĩa nghiệp vụ
 * rõ ràng hơn là tái dùng cột timestamp kỹ thuật của Eloquent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_thread_reads', function (Blueprint $table) {
            $table->id();
            $table->string('commentable_type');
            $table->unsignedBigInteger('commentable_id');

            $table->foreignId('user_id')
                ->constrained('users', 'id', 'comment_thread_reads_user_fk')
                ->cascadeOnDelete();

            $table->timestamp('last_read_at');
            $table->timestamps();

            $table->unique(['commentable_type', 'commentable_id', 'user_id'], 'comment_thread_reads_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_thread_reads');
    }
};
