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
        $this->assertSame('private', $list[0]['type']);
    }

    public function test_creating_a_group_adds_members_and_an_opening_message(): void
    {
        $me = $this->makeUser('Mai');
        $binh = $this->makeUser('Bình');
        $lan = $this->makeUser('Lan');

        $response = $this->actingAs($me)
            ->postJson('/api/chat/conversations/groups', [
                'title' => 'Tổ chuyên môn',
                'user_ids' => [$binh->id, $lan->id],
            ])
            ->assertCreated();

        $conversationId = $response->json('conversation.id');
        $this->assertSame('group', $response->json('conversation.type'));
        $this->assertSame('Tổ chuyên môn', $response->json('conversation.title'));
        $this->assertSame(3, $response->json('conversation.member_count'));
        $this->assertSame('Mai đã tạo nhóm «Tổ chuyên môn»', $response->json('conversation.last_message.message'));

        $this->assertDatabaseHas('conversation_members', [
            'conversation_id' => $conversationId,
            'user_id' => $lan->id,
        ]);

        $lanList = $this->actingAs($lan)
            ->getJson('/api/chat/conversations')
            ->assertOk()
            ->json('conversations');

        $this->assertSame('Tổ chuyên môn', $lanList[0]['title']);
        $this->assertSame(1, $lanList[0]['unread_count']);
    }

    public function test_a_group_requires_someone_else(): void
    {
        $me = $this->makeUser('Mai');

        $this->actingAs($me)
            ->postJson('/api/chat/conversations/groups', [
                'title' => 'Một mình',
                'user_ids' => [$me->id],
            ])
            ->assertStatus(422);
    }

    public function test_clearing_history_hides_messages_only_for_the_actor(): void
    {
        $me = $this->makeUser('Mai');
        $other = $this->makeUser('Bình');

        $conversationId = $this->actingAs($me)
            ->postJson('/api/chat/conversations', ['user_id' => $other->id])
            ->json('conversation.id');

        $this->actingAs($other)
            ->postJson("/api/chat/conversations/{$conversationId}/messages", ['message' => 'Giữ lại phía kia'])
            ->assertCreated();

        $this->actingAs($me)
            ->deleteJson("/api/chat/conversations/{$conversationId}/history")
            ->assertOk();

        $mine = $this->actingAs($me)
            ->getJson("/api/chat/conversations/{$conversationId}/messages")
            ->assertOk()
            ->json('messages');
        $theirs = $this->actingAs($other)
            ->getJson("/api/chat/conversations/{$conversationId}/messages")
            ->assertOk()
            ->json('messages');

        $this->assertSame([], $mine);
        $this->assertSame('Giữ lại phía kia', $theirs[0]['message']);
    }

    public function test_disbanding_a_group_removes_it_for_every_member(): void
    {
        $me = $this->makeUser('Mai');
        $other = $this->makeUser('Bình');

        $conversationId = $this->actingAs($me)
            ->postJson('/api/chat/conversations/groups', [
                'title' => 'Nhóm tạm',
                'user_ids' => [$other->id],
            ])
            ->json('conversation.id');

        $this->actingAs($me)
            ->deleteJson("/api/chat/conversations/{$conversationId}")
            ->assertOk();

        $this->assertDatabaseMissing('conversations', ['id' => $conversationId]);

        $list = $this->actingAs($other)
            ->getJson('/api/chat/conversations')
            ->assertOk()
            ->json('conversations');

        $this->assertSame([], $list);
    }

    public function test_only_the_group_owner_can_disband(): void
    {
        $owner = $this->makeUser('Mai');
        $other = $this->makeUser('Bình');

        $conversationId = $this->actingAs($owner)
            ->postJson('/api/chat/conversations/groups', [
                'title' => 'Nhóm chủ',
                'user_ids' => [$other->id],
            ])
            ->json('conversation.id');

        $this->actingAs($other)
            ->deleteJson("/api/chat/conversations/{$conversationId}")
            ->assertForbidden();

        $this->assertDatabaseHas('conversations', [
            'id' => $conversationId,
            'created_by' => $owner->id,
        ]);
    }

    public function test_a_private_chat_cannot_be_disbanded(): void
    {
        $me = $this->makeUser('Mai');
        $other = $this->makeUser('Bình');

        $conversationId = $this->actingAs($me)
            ->postJson('/api/chat/conversations', ['user_id' => $other->id])
            ->json('conversation.id');

        $this->actingAs($me)
            ->deleteJson("/api/chat/conversations/{$conversationId}")
            ->assertStatus(422);

        $this->assertDatabaseHas('conversations', ['id' => $conversationId]);
    }
}
