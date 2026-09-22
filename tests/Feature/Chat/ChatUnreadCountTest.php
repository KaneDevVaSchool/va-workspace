<?php

namespace Tests\Feature\Chat;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatUnreadCountTest extends TestCase
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

    public function test_unread_count_reflects_unread_messages_from_the_other_user(): void
    {
        $me = $this->makeUser('Mai');
        $other = $this->makeUser('Bình');
        $conversationId = $this->openConversation($me, $other);

        $this->actingAs($other)
            ->postJson("/api/chat/conversations/{$conversationId}/messages", ['message' => 'Tin 1'])
            ->assertCreated();
        $this->actingAs($other)
            ->postJson("/api/chat/conversations/{$conversationId}/messages", ['message' => 'Tin 2'])
            ->assertCreated();

        $this->actingAs($me)
            ->getJson('/api/chat/unread-count')
            ->assertOk()
            ->assertJson(['unread_total' => 2]);
    }

    public function test_marking_a_conversation_read_updates_last_read_at_and_clears_unread_count(): void
    {
        $me = $this->makeUser('Mai');
        $other = $this->makeUser('Bình');
        $conversationId = $this->openConversation($me, $other);

        $this->actingAs($other)
            ->postJson("/api/chat/conversations/{$conversationId}/messages", ['message' => 'Tin 1'])
            ->assertCreated();

        $this->actingAs($me)
            ->postJson("/api/chat/conversations/{$conversationId}/read")
            ->assertOk()
            ->assertJson(['unread_count' => 0]);

        $this->assertDatabaseHas('conversation_members', [
            'conversation_id' => $conversationId,
            'user_id' => $me->id,
        ]);

        $member = \Modules\Chat\App\Models\ConversationMember::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $me->id)
            ->first();

        $this->assertNotNull($member->last_read_at);

        $this->actingAs($me)
            ->getJson('/api/chat/unread-count')
            ->assertOk()
            ->assertJson(['unread_total' => 0]);
    }

    public function test_a_users_own_messages_do_not_count_as_unread_for_themselves(): void
    {
        $me = $this->makeUser('Mai');
        $other = $this->makeUser('Bình');
        $conversationId = $this->openConversation($me, $other);

        $this->actingAs($me)
            ->postJson("/api/chat/conversations/{$conversationId}/messages", ['message' => 'Tin của tôi'])
            ->assertCreated();

        $this->actingAs($me)
            ->getJson('/api/chat/unread-count')
            ->assertOk()
            ->assertJson(['unread_total' => 0]);
    }
}
