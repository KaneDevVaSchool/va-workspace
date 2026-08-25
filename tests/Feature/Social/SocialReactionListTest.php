<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialReactionListTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name = 'Người dùng'): User
    {
        return User::factory()->create([
            'name' => $name,
            'status' => 'active',
        ]);
    }

    public function test_can_list_people_who_reacted_to_a_post(): void
    {
        $author = $this->makeUser('Tác giả');
        $hahaUser = $this->makeUser('Nguyen Van A');
        $likeUser = $this->makeUser('Tran Thi B');

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài viết'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($hahaUser)
            ->postJson("/api/social/posts/{$postId}/reactions", ['type' => 'haha'])
            ->assertOk();

        $this->actingAs($likeUser)
            ->postJson("/api/social/posts/{$postId}/reactions", ['type' => 'like'])
            ->assertOk();

        $users = $this->actingAs($author)
            ->getJson("/api/social/posts/{$postId}/reactions")
            ->assertOk()
            ->assertJsonCount(2, 'users')
            ->json('users');

        $namesByType = collect($users)->mapWithKeys(fn (array $item) => [$item['type'] => $item['user']['name']]);
        $this->assertSame('Nguyen Van A', $namesByType['haha']);
        $this->assertSame('Tran Thi B', $namesByType['like']);

        $this->actingAs($author)
            ->getJson("/api/social/posts/{$postId}/reactions?type=haha")
            ->assertOk()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.type', 'haha')
            ->assertJsonPath('users.0.user.name', 'Nguyen Van A');
    }

    public function test_can_list_people_who_reacted_to_a_comment(): void
    {
        $author = $this->makeUser('Tác giả');
        $reactor = $this->makeUser('Nguyen Van A');

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài viết'])
            ->assertCreated()
            ->json('post.id');

        $commentId = $this->actingAs($author)
            ->postJson("/api/social/posts/{$postId}/comments", ['content' => 'Bình luận'])
            ->assertCreated()
            ->json('comment.id');

        $this->actingAs($reactor)
            ->postJson("/api/social/comments/{$commentId}/reactions", ['type' => 'love'])
            ->assertOk();

        $this->actingAs($author)
            ->getJson("/api/social/comments/{$commentId}/reactions")
            ->assertOk()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.type', 'love')
            ->assertJsonPath('users.0.user.name', 'Nguyen Van A');
    }

    public function test_rejects_invalid_reaction_type_filter(): void
    {
        $user = $this->makeUser();

        $postId = $this->actingAs($user)
            ->postJson('/api/social/posts', ['content' => 'Bài viết'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($user)
            ->getJson("/api/social/posts/{$postId}/reactions?type=unknown")
            ->assertUnprocessable();
    }
}
