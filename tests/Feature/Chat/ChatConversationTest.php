<?php

namespace Tests\Feature\Chat;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Chat\App\Models\Conversation;
use Tests\TestCase;

class ChatConversationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name = 'Người dùng'): User
    {
        return User::factory()->create([
            'name' => $name,
            'status' => 'active',
        ]);
    }

    public function test_opening_a_conversation_with_another_user_creates_it(): void
    {
        $me = $this->makeUser('Mai');
        $other = $this->makeUser('Bình');

        $response = $this->actingAs($me)
            ->postJson('/api/chat/conversations', ['user_id' => $other->id])
            ->assertOk();

        $conversationId = $response->json('conversation.id');

        $this->assertDatabaseHas('conversation_members', [
            'conversation_id' => $conversationId,
            'user_id' => $me->id,
        ]);
        $this->assertDatabaseHas('conversation_members', [
            'conversation_id' => $conversationId,
            'user_id' => $other->id,
        ]);
    }

    public function test_opening_a_conversation_with_the_same_user_twice_returns_the_same_conversation(): void
    {
        $me = $this->makeUser('Mai');
        $other = $this->makeUser('Bình');

        $first = $this->actingAs($me)
            ->postJson('/api/chat/conversations', ['user_id' => $other->id])
            ->assertOk()
            ->json('conversation.id');

        $second = $this->actingAs($other)
            ->postJson('/api/chat/conversations', ['user_id' => $me->id])
            ->assertOk()
            ->json('conversation.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, Conversation::query()->count());
    }

    public function test_a_user_cannot_open_a_conversation_with_themselves(): void
    {
        $me = $this->makeUser('Mai');

        $this->actingAs($me)
            ->postJson('/api/chat/conversations', ['user_id' => $me->id])
            ->assertStatus(422);
    }

    public function test_listing_conversations_returns_last_message_and_unread_count(): void
    {
        $me = $this->makeUser('Mai');
        $other = $this->makeUser('Bình');

        $conversationId = $this->actingAs($me)
            ->postJson('/api/chat/conversations', ['user_id' => $other->id])
            ->json('conversation.id');

        $this->actingAs($other)
            ->postJson("/api/chat/conversations/{$conversationId}/messages", ['message' => 'Chào bạn'])
            ->assertCreated();

        $list = $this->actingAs($me)
            ->getJson('/api/chat/conversations')
            ->assertOk()
            ->json('conversations');

        $this->assertCount(1, $list);
        $this->assertSame('Chào bạn', $list[0]['last_message']['message']);
        $this->assertSame(1, $list[0]['unread_count']);
    }
}
