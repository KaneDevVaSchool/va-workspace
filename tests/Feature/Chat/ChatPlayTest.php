<?php

namespace Tests\Feature\Chat;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Chat\App\Models\Message;
use Tests\TestCase;

class ChatPlayTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name = 'Người dùng'): User
    {
        return User::factory()->create([
            'name' => $name,
            'status' => 'active',
        ]);
    }

    public function test_mood_is_visible_to_the_other_person(): void
    {
        $an = $this->makeUser('An');
        $binh = $this->makeUser('Bình');

        $this->actingAs($an)
            ->postJson('/api/chat/conversations', ['user_id' => $binh->id])
            ->assertOk();

        $this->actingAs($an)
            ->putJson('/api/chat/mood', ['mood' => 'book'])
            ->assertOk()
            ->assertJsonPath('mood', 'book');

        $this->actingAs($binh)
            ->getJson('/api/chat/conversations')
            ->assertOk()
            ->assertJsonPath('conversations.0.other_user.mood', 'book');

        $this->actingAs($an)
            ->putJson('/api/chat/mood', ['mood' => 'nope'])
            ->assertStatus(422);

        $this->actingAs($an)
            ->putJson('/api/chat/mood', ['mood' => null])
            ->assertOk()
            ->assertJsonPath('mood', null);
    }

    public function test_direct_streak_counts_days_both_people_wrote(): void
    {
        $an = $this->makeUser('An');
        $binh = $this->makeUser('Bình');
        $conversationId = $this->actingAs($an)
            ->postJson('/api/chat/conversations', ['user_id' => $binh->id])
            ->json('conversation.id');

        $this->actingAs($an)->postJson("/api/chat/conversations/{$conversationId}/messages", ['message' => 'Hôm qua']);
        $this->actingAs($binh)->postJson("/api/chat/conversations/{$conversationId}/messages", ['message' => 'Hôm qua nhé']);
        Message::query()->where('conversation_id', $conversationId)->update([
            'created_at' => now()->subDay(),
        ]);
        $this->actingAs($an)->postJson("/api/chat/conversations/{$conversationId}/messages", ['message' => 'Hôm nay']);
        $this->actingAs($binh)->postJson("/api/chat/conversations/{$conversationId}/messages", ['message' => 'Hôm nay nhé']);

        $this->actingAs($an)
            ->getJson('/api/chat/conversations')
            ->assertOk()
            ->assertJsonPath('conversations.0.streak', 2);
    }

    public function test_group_owner_sets_accent(): void
    {
        $owner = $this->makeUser('Mai');
        $member = $this->makeUser('Lan');
        $conversationId = $this->actingAs($owner)
            ->postJson('/api/chat/conversations/groups', [
                'title' => 'Tổ văn',
                'user_ids' => [$member->id],
            ])
            ->assertCreated()
            ->json('conversation.id');

        $this->actingAs($member)
            ->patchJson("/api/chat/conversations/{$conversationId}/accent", ['accent' => 'teal'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->patchJson("/api/chat/conversations/{$conversationId}/accent", ['accent' => 'teal'])
            ->assertOk()
            ->assertJsonPath('conversation.accent', 'teal');

        $this->actingAs($member)
            ->getJson('/api/chat/conversations')
            ->assertOk()
            ->assertJsonPath('conversations.0.accent', 'teal');
    }
}
