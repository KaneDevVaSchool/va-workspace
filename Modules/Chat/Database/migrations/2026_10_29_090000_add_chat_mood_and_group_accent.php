<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conversations') && ! Schema::hasColumn('conversations', 'accent')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->string('accent', 16)->nullable();
            });
        }

        if (! Schema::hasTable('chat_user_moods')) {
            Schema::create('chat_user_moods', function (Blueprint $table) {
                $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
                $table->string('mood', 16);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_user_moods');

        if (Schema::hasTable('conversations') && Schema::hasColumn('conversations', 'accent')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->dropColumn('accent');
            });
        }
    }
};
