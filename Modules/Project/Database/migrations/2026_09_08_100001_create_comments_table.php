<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bình luận (Thảo luận) — polymorphic dùng chung cho Task và Project, khuôn
 * theo social_post_comments (Modules/Social) nhưng bỏ hẳn phần đặc thù bài
 * đăng mạng xã hội (post_id cứng → commentable_type/commentable_id).
 *
 * commentable_type lưu qua morph map ('task'|'project', đăng ký trong
 * ProjectServiceProvider::boot()), KHÔNG lưu full class name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->string('commentable_type');
            $table->unsignedBigInteger('commentable_id');

            // Reply tối đa 2 cấp — tự tham chiếu, giống parent_comment_id của Social.
            $table->foreignId('parent_comment_id')->nullable()
                ->constrained('comments', 'id', 'comments_parent_fk')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users', 'id', 'comments_user_fk')
                ->cascadeOnDelete();

            $table->foreignId('mentioned_user_id')->nullable()
                ->constrained('users', 'id', 'comments_mentioned_user_fk')
                ->nullOnDelete();

            // HTML đã sanitize qua CommentSanitizer (không phải text thô) —
            // cần giữ định dạng rich-text + span mention.
            $table->text('content');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['commentable_type', 'commentable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
