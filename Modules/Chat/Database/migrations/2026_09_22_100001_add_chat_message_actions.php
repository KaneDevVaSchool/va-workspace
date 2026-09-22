<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('reply_to_id')->nullable()->after('sender_id')->index();
            $table->string('sticker_id', 64)->nullable()->after('message');
            $table->timestamp('edited_at')->nullable()->after('message_type');
            $table->timestamp('recalled_at')->nullable()->after('edited_at');
        });

        Schema::create('message_hides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['message_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_hides');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['reply_to_id']);
            $table->dropColumn(['reply_to_id', 'sticker_id', 'edited_at', 'recalled_at']);
        });
    }
};
