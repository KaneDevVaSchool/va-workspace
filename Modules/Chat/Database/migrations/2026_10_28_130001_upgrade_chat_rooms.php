<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function prefixedTable(string $table): string
    {
        return Schema::getConnection()->getTablePrefix().$table;
    }

    private function alterMessagesEnum(string $enumList): void
    {
        $table = $this->prefixedTable('messages');
        DB::statement("ALTER TABLE `{$table}` MODIFY message_type ENUM({$enumList}) NOT NULL DEFAULT 'text'");
    }

    public function up(): void
    {
        if (! Schema::hasTable('messages')) {
            throw new RuntimeException(
                'Bảng `messages` chưa tồn tại. Chạy lại `php artisan migrate` sau khi deploy migration '.
                '2026_10_28_120000_ensure_chat_core_tables, hoặc kiểm tra bảng `migrations` và schema Chat cơ bản.'
            );
        }

        Schema::table('conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('conversations', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('title')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('conversations', 'pinned_message_id')) {
                $table->foreignId('pinned_message_id')->nullable()->after('created_by')->constrained('messages')->nullOnDelete();
            }
            if (! Schema::hasColumn('conversations', 'disappear_seconds')) {
                $table->unsignedInteger('disappear_seconds')->nullable()->after('pinned_message_id');
            }
        });

        Schema::table('conversation_members', function (Blueprint $table) {
            if (! Schema::hasColumn('conversation_members', 'pinned_at')) {
                $table->timestamp('pinned_at')->nullable()->after('last_read_at');
            }
            if (! Schema::hasColumn('conversation_members', 'muted_until')) {
                $table->timestamp('muted_until')->nullable()->after('pinned_at');
            }
        });

        if (Schema::hasTable('message_attachments') && ! Schema::hasColumn('message_attachments', 'disk')) {
            Schema::table('message_attachments', function (Blueprint $table) {
                $table->string('disk', 16)->default('public')->after('file_path');
            });
        }

        $addedExpiresAt = false;

        Schema::table('messages', function (Blueprint $table) use (&$addedExpiresAt) {
            if (! Schema::hasColumn('messages', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('recalled_at');
                $addedExpiresAt = true;
            }
            if (! Schema::hasColumn('messages', 'voice_duration')) {
                $table->unsignedSmallInteger('voice_duration')->nullable()->after('expires_at');
            }
        });

        if ($addedExpiresAt) {
            Schema::table('messages', function (Blueprint $table) {
                $table->index('expires_at');
            });
        }

        $this->alterMessagesEnum("'text','image','file','system','voice','poll'");

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('updated_at');
            }
        });

        if (! Schema::hasTable('message_reads')) {
            Schema::create('message_reads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('read_at')->useCurrent();
                $table->timestamps();

                $table->unique(['message_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('message_mentions')) {
            Schema::create('message_mentions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['message_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('chat_polls')) {
            Schema::create('chat_polls', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
                $table->string('question', 200);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('chat_poll_options')) {
            Schema::create('chat_poll_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('poll_id')->constrained('chat_polls')->cascadeOnDelete();
                $table->string('label', 80);
                $table->unsignedTinyInteger('position')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('chat_poll_votes')) {
            Schema::create('chat_poll_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('poll_id')->constrained('chat_polls')->cascadeOnDelete();
                $table->foreignId('option_id')->constrained('chat_poll_options')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['poll_id', 'user_id']);
            });
        }

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

        if (Schema::hasColumn('users', 'last_seen_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('last_seen_at');
            });
        }

        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'message_type')) {
            $this->alterMessagesEnum("'text','image','file','system'");
        }

        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                if (Schema::hasColumn('messages', 'expires_at')) {
                    $table->dropIndex(['expires_at']);
                }
                $drop = array_values(array_filter(
                    ['expires_at', 'voice_duration'],
                    fn (string $col) => Schema::hasColumn('messages', $col)
                ));
                if ($drop !== []) {
                    $table->dropColumn($drop);
                }
            });
        }

        if (Schema::hasTable('message_attachments') && Schema::hasColumn('message_attachments', 'disk')) {
            Schema::table('message_attachments', function (Blueprint $table) {
                $table->dropColumn('disk');
            });
        }

        Schema::table('conversation_members', function (Blueprint $table) {
            $drop = array_values(array_filter(
                ['pinned_at', 'muted_until'],
                fn (string $col) => Schema::hasColumn('conversation_members', $col)
            ));
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'pinned_message_id')) {
                $table->dropConstrainedForeignId('pinned_message_id');
            }
            if (Schema::hasColumn('conversations', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
            if (Schema::hasColumn('conversations', 'disappear_seconds')) {
                $table->dropColumn('disappear_seconds');
            }
        });
    }
};
