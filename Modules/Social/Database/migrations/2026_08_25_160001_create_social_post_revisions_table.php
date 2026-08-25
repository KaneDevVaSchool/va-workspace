<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->timestamp('content_updated_at')->nullable()->after('content');
        });

        Schema::create('social_post_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('social_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('content')->nullable();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->index(['post_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_post_revisions');

        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropColumn('content_updated_at');
        });
    }
};
