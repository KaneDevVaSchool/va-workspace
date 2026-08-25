<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_polls', function (Blueprint $table) {
            $table->string('title', 200)->nullable()->after('post_id');
            $table->text('content')->nullable()->after('title');
            $table->string('image_path')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('social_polls', function (Blueprint $table) {
            $table->dropColumn(['title', 'content', 'image_path']);
        });
    }
};
