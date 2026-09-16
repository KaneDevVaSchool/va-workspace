<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_link_previews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('social_posts')->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('provider', 20);
            $table->string('external_id', 191)->nullable();
            $table->string('title', 500)->nullable();
            $table->string('thumbnail_url', 2048)->nullable();
            $table->string('embed_url', 2048)->nullable();
            $table->boolean('is_live')->default(false);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->index(['post_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_link_previews');
    }
};
