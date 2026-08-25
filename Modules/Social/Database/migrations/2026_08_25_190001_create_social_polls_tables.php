<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->unique()->constrained('social_posts')->cascadeOnDelete();
            $table->boolean('allow_multiple')->default(false);
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
        });

        Schema::create('social_poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('social_polls')->cascadeOnDelete();
            $table->string('label', 200);
            $table->unsignedTinyInteger('position');
            $table->timestamps();
        });

        Schema::create('social_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('social_polls')->cascadeOnDelete();
            $table->foreignId('option_id')->constrained('social_poll_options')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['option_id', 'user_id']);
            $table->index(['poll_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_poll_votes');
        Schema::dropIfExists('social_poll_options');
        Schema::dropIfExists('social_polls');
    }
};
