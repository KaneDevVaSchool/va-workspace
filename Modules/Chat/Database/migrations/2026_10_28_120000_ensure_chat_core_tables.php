<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Heals environments where Chat migrations are recorded in `migrations`
 * but base tables were never created (partial DB restore, manual deletes, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();
                $table->enum('type', ['private', 'group'])->default('private');
                $table->string('title', 80)->nullable();
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('conversations', 'title')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->string('title', 80)->nullable()->after('type');
            });
        }

        if (! Schema::hasTable('conversation_members')) {
            Schema::create('conversation_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('joined_at')->useCurrent();
                $table->timestamp('last_read_at')->nullable();
                $table->timestamps();

                $table->unique(['conversation_id', 'user_id']);
                $table->index('user_id');
            });
        }

        if (! Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('reply_to_id')->nullable()->index();
                $table->text('message');
                $table->string('sticker_id', 64)->nullable();
                $table->enum('message_type', ['text', 'image', 'file', 'system'])->default('text');
                $table->timestamp('edited_at')->nullable();
                $table->timestamp('recalled_at')->nullable();
                $table->timestamps();

                $table->index(['conversation_id', 'created_at']);
            });
        } else {
            Schema::table('messages', function (Blueprint $table) {
                if (! Schema::hasColumn('messages', 'reply_to_id')) {
                    $table->unsignedBigInteger('reply_to_id')->nullable()->after('sender_id')->index();
                }
                if (! Schema::hasColumn('messages', 'sticker_id')) {
                    $table->string('sticker_id', 64)->nullable()->after('message');
                }
                if (! Schema::hasColumn('messages', 'edited_at')) {
                    $table->timestamp('edited_at')->nullable()->after('message_type');
                }
                if (! Schema::hasColumn('messages', 'recalled_at')) {
                    $table->timestamp('recalled_at')->nullable()->after('edited_at');
                }
            });
        }

        if (! Schema::hasTable('message_hides')) {
            Schema::create('message_hides', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['message_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('message_attachments')) {
            Schema::create('message_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
                $table->string('file_path');
                $table->string('file_name');
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('mime_type')->nullable();
                $table->string('type', 16)->default('file');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: repair migration must not drop production chat data.
    }
};
