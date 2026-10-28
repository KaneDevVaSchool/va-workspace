<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('media_path');
            $table->string('disk', 16)->default('local');
            $table->string('media_kind', 16);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['expires_at', 'user_id']);
        });

        Schema::create('social_story_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained('social_stories')->cascadeOnDelete();
            $table->foreignId('viewer_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('viewed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['story_id', 'viewer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_story_views');
        Schema::dropIfExists('social_stories');
    }
};
