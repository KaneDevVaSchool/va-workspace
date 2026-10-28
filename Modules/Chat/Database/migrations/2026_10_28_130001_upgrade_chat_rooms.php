<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('title')->constrained('users')->nullOnDelete();
            $table->foreignId('pinned_message_id')->nullable()->after('created_by')->constrained('messages')->nullOnDelete();
            $table->unsignedInteger('disappear_seconds')->nullable()->after('pinned_message_id');
        });

        Schema::table('conversation_members', function (Blueprint $table) {
            $table->timestamp('pinned_at')->nullable()->after('last_read_at');
            $table->timestamp('muted_until')->nullable()->after('pinned_at');
        });

        Schema::table('message_attachments', function (Blueprint $table) {
            $table->string('disk', 16)->default('public')->after('file_path');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('recalled_at');
            $table->unsignedSmallInteger('voice_duration')->nullable()->after('expires_at');
            $table->index('expires_at');
        });

        DB::statement("ALTER TABLE messages MODIFY message_type ENUM('text','image','file','system','voice','poll') NOT NULL DEFAULT 'text'");

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('updated_at');
        });

        Schema::create('message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();
            $table->timestamps();

            $table->unique(['message_id', 'user_id']);
        });

        Schema::create('message_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['message_id', 'user_id']);
        });

        Schema::create('chat_polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->string('question', 200);
            $table->timestamps();
        });

        Schema::create('chat_poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('chat_polls')->cascadeOnDelete();
            $table->string('label', 80);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('chat_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('chat_polls')->cascadeOnDelete();
            $table->foreignId('option_id')->constrained('chat_poll_options')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['poll_id', 'user_id']);
        });

        $groups = DB::table('conversations')->where('type', 'group')->whereNull('created_by')->pluck('id');
        foreach ($groups as $conversationId) {
            $ownerId = DB::table('conversation_members')
                ->where('conversation_id', $conversationId)
                ->orderBy('joined_at')
                ->orderBy('id')
                ->value('user_id');
            if ($ownerId) {
                DB::table('conversations')->where('id', $conversationId)->update(['created_by' => $ownerId]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_poll_votes');
        Schema::dropIfExists('chat_poll_options');
        Schema::dropIfExists('chat_polls');
        Schema::dropIfExists('message_mentions');
        Schema::dropIfExists('message_reads');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_seen_at');
        });

        DB::statement("ALTER TABLE messages MODIFY message_type ENUM('text','image','file','system') NOT NULL DEFAULT 'text'");

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['expires_at', 'voice_duration']);
        });

        Schema::table('message_attachments', function (Blueprint $table) {
            $table->dropColumn('disk');
        });

        Schema::table('conversation_members', function (Blueprint $table) {
            $table->dropColumn(['pinned_at', 'muted_until']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pinned_message_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('disappear_seconds');
        });
    }
};
