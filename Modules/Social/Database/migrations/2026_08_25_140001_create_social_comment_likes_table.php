<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_comment_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained('social_post_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reaction_type', 20)->default('like');
            $table->timestamps();

            $table->unique(['comment_id', 'user_id'], 'social_comment_likes_comment_user_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_comment_likes');
    }
};
