<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_post_comments', function (Blueprint $table) {
            $table->json('attachments')->nullable()->after('content');
            $table->foreignId('mentioned_user_id')->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('social_post_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mentioned_user_id');
            $table->dropColumn('attachments');
        });
    }
};
