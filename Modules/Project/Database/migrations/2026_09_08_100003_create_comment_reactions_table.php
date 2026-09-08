<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thả cảm xúc cho bình luận — tương đương social_comment_likes, đổi tên rõ
 * nghĩa hơn (comment đã tự polymorphic nên chỉ cần trỏ thẳng comment_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')
                ->constrained('comments', 'id', 'comment_reactions_comment_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users', 'id', 'comment_reactions_user_fk')
                ->cascadeOnDelete();
            $table->string('reaction_type', 20)->default('like');
            $table->timestamps();

            $table->unique(['comment_id', 'user_id'], 'comment_reactions_comment_user_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_reactions');
    }
};
