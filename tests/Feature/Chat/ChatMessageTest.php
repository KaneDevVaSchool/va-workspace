<?php

namespace Tests\Feature\Chat;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Modules\Chat\App\Events\MessageSent;
use Modules\Chat\App\Models\MessageAttachment;
use Tests\TestCase;

class ChatMessageTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name = 'Người dùng'): User
    {
        return User::factory()->create([
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function openConversation(User $a, User $b): int
    {
        return $this->actingAs($a)
            ->postJson('/api/chat/conversations', ['user_id' => $b->id])
            ->json('conversation.id');
    }

    public function test_sending_a_message_persists_it_and_dispatches_broadcast_event(): void
    {
        Event::fake([MessageSent::class]);

        $sender = $this->makeUser('Mai');
        $recipient = $this->makeUser('Bình');
        $conversationId = $this->openConversation($sender, $recipient);

        $this->actingAs($sender)
            ->postJson("/api/chat/conversations/{$conversationId}/messages", ['message' => 'Xin chào'])
            ->assertCreated();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'sender_id' => $sender->id,
            'message' => 'Xin chào',
        ]);

        Event::assertDispatched(
            MessageSent::class,
            fn (MessageSent $event) => $event->conversationId === $conversationId,
        );
    }

    public function test_a_user_not_in_the_conversation_cannot_read_messages(): void
    {
        $a = $this->makeUser('Mai');
        $b = $this->makeUser('Bình');
        $outsider = $this->makeUser('Cường');
        $conversationId = $this->openConversation($a, $b);

        $this->actingAs($outsider)
            ->getJson("/api/chat/conversations/{$conversationId}/messages")
            ->assertStatus(403);
    }

    public function test_a_user_not_in_the_conversation_cannot_send_a_message(): void
    {
        $a = $this->makeUser('Mai');
        $b = $this->makeUser('Bình');
        $outsider = $this->makeUser('Cường');
        $conversationId = $this->openConversation($a, $b);

        $this->actingAs($outsider)
            ->postJson("/api/chat/conversations/{$conversationId}/messages", ['message' => 'Xin chào'])
            ->assertStatus(403);
    }

    public function test_message_over_5000_characters_is_rejected(): void
    {
        $sender = $this->makeUser('Mai');
        $recipient = $this->makeUser('Bình');
        $conversationId = $this->openConversation($sender, $recipient);

        $this->actingAs($sender)
            ->postJson("/api/chat/conversations/{$conversationId}/messages", [
                'message' => str_repeat('a', 5001),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    public function test_sending_a_message_notifies_the_recipient(): void
    {
        $sender = $this->makeUser('Mai');
        $recipient = $this->makeUser('Bình');
        $conversationId = $this->openConversation($sender, $recipient);

        $this->actingAs($sender)
            ->postJson("/api/chat/conversations/{$conversationId}/messages", ['message' => 'Xin chào'])
            ->assertCreated();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $recipient->id,
            'actor_id' => $sender->id,
            'type' => 'chat_message',
            'body' => 'Xin chào',
        ]);
    }

    public function test_sender_can_edit_own_message(): void
    {
        $sender = $this->makeUser('Mai');
        $recipient = $this->makeUser('Bình');
        $conversationId = $this->openConversation($sender, $recipient);
        $messageId = $this->send($sender, $conversationId, 'Bản nháp');

        $edited = $this->actingAs($sender)
            ->patchJson("/api/chat/conversations/{$conversationId}/messages/{$messageId}", [
                'message' => 'Bản đã sửa',
            ])
            ->assertOk()
            ->assertJsonPath('message.message', 'Bản đã sửa');

        $this->assertIsString($edited->json('message.edited_at'));

        $this->actingAs($recipient)
            ->patchJson("/api/chat/conversations/{$conversationId}/messages/{$messageId}", [
                'message' => 'Không được',
            ])
            ->assertStatus(403);
    }

    public function test_sender_can_recall_a_message_for_both_people(): void
    {
        $sender = $this->makeUser('Mai');
        $recipient = $this->makeUser('Bình');
        $conversationId = $this->openConversation($sender, $recipient);
        $messageId = $this->send($sender, $conversationId, 'Nhầm người');

        $this->actingAs($sender)
            ->postJson("/api/chat/conversations/{$conversationId}/messages/{$messageId}/recall")
            ->assertOk()
            ->assertJsonPath('message.message', null);

        $listed = $this->actingAs($recipient)
            ->getJson("/api/chat/conversations/{$conversationId}/messages")
            ->assertOk()
            ->assertJsonPath('messages.0.message', null);

        $this->assertIsString($listed->json('messages.0.recalled_at'));

        $this->assertDatabaseMissing('messages', [
            'id' => $messageId,
            'message' => 'Nhầm người',
        ]);
    }

    public function test_delete_hides_the_message_only_for_the_actor(): void
    {
        $sender = $this->makeUser('Mai');
        $recipient = $this->makeUser('Bình');
        $conversationId = $this->openConversation($sender, $recipient);
        $messageId = $this->send($sender, $conversationId, 'Chỉ mình tôi xoá');

        $this->actingAs($recipient)
            ->deleteJson("/api/chat/conversations/{$conversationId}/messages/{$messageId}")
            ->assertOk();

        $this->actingAs($recipient)
            ->getJson("/api/chat/conversations/{$conversationId}/messages")
            ->assertOk()
            ->assertJsonCount(0, 'messages');

        $this->actingAs($sender)
            ->getJson("/api/chat/conversations/{$conversationId}/messages")
            ->assertOk()
            ->assertJsonPath('messages.0.message', 'Chỉ mình tôi xoá');
    }

    public function test_reply_and_sticker_are_stored_and_push_a_notification(): void
    {
        $sender = $this->makeUser('Mai');
        $recipient = $this->makeUser('Bình');
        $conversationId = $this->openConversation($sender, $recipient);
        $parentId = $this->send($sender, $conversationId, 'Câu hỏi');

        $this->actingAs($recipient)
            ->postJson("/api/chat/conversations/{$conversationId}/messages", [
                'message' => 'Trả lời đây',
                'reply_to_id' => $parentId,
            ])
            ->assertCreated()
            ->assertJsonPath('message.reply_to.id', $parentId)
            ->assertJsonPath('message.reply_to.message', 'Câu hỏi');

        $this->actingAs($sender)
            ->postJson("/api/chat/conversations/{$conversationId}/messages", [
                'message' => '😀',
                'message_type' => 'sticker',
                'sticker_id' => '1f600',
            ])
            ->assertCreated()
            ->assertJsonPath('message.message_type', 'sticker')
            ->assertJsonPath('message.sticker_id', '1f600');

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $sender->id,
            'type' => 'chat_message',
            'body' => 'Đã trả lời: Trả lời đây',
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $recipient->id,
            'type' => 'chat_message',
            'body' => 'Đã gửi một sticker',
        ]);
    }

    public function test_a_message_can_include_files_and_recall_removes_them(): void
    {
        Storage::fake('public');

        $sender = $this->makeUser('Mai');
        $recipient = $this->makeUser('Bình');
        $conversationId = $this->openConversation($sender, $recipient);

        $this->actingAs($sender)
            ->post("/api/chat/conversations/{$conversationId}/messages", [
                'message' => 'Kèm file',
                'attachments' => [
                    UploadedFile::fake()->create('bao-cao.pdf', 20, 'application/pdf'),
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('message.attachments.0.name', 'bao-cao.pdf')
            ->assertJsonPath('message.attachments.0.type', 'file');

        $image = $this->actingAs($sender)
            ->post("/api/chat/conversations/{$conversationId}/messages", [
                'attachments' => [
                    UploadedFile::fake()->image('anh.png'),
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('message.message', null)
            ->assertJsonPath('message.attachments.0.type', 'image');

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $recipient->id,
            'type' => 'chat_message',
            'body' => 'Ảnh',
        ]);

        $path = MessageAttachment::query()->where('file_name', 'anh.png')->value('file_path');
        Storage::disk('public')->assertExists($path);

        $this->actingAs($sender)
            ->postJson("/api/chat/conversations/{$conversationId}/messages/{$image->json('message.id')}/recall")
            ->assertOk()
            ->assertJsonPath('message.attachments', []);

        Storage::disk('public')->assertMissing($path);
    }

    private function send(User $sender, int $conversationId, string $message): int
    {
        return $this->actingAs($sender)
            ->postJson("/api/chat/conversations/{$conversationId}/messages", ['message' => $message])
            ->assertCreated()
            ->json('message.id');
    }
}
