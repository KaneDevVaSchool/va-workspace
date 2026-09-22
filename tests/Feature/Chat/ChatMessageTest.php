<?php

namespace Tests\Feature\Chat;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Chat\App\Events\MessageSent;
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
        ]);
    }
}
