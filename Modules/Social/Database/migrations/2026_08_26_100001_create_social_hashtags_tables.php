<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_hashtags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->string('label', 64);
            $table->unsignedInteger('posts_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('last_used_at');
        });

        Schema::create('social_hashtag_post', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hashtag_id')->constrained('social_hashtags')->cascadeOnDelete();
            $table->foreignId('post_id')->constrained('social_posts')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['hashtag_id', 'post_id']);
            $table->index('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_hashtag_post');
        Schema::dropIfExists('social_hashtags');
    }
};
