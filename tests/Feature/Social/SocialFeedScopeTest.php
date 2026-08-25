<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialFeedScopeTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    public function test_profile_stats_count_own_activity(): void
    {
        $author = $this->makeUser();
        $other = $this->makeUser();

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài của tôi'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($other)
            ->postJson("/api/social/posts/{$postId}/reactions", ['type' => 'like'])
            ->assertOk();

        $this->actingAs($author)
            ->postJson("/api/social/posts/{$postId}/comments", ['content' => 'Tự bình luận'])
            ->assertCreated();

        $this->actingAs($other)
            ->postJson('/api/social/posts', ['content' => 'Bài người khác'])
            ->assertCreated();

        $this->actingAs($author)
            ->getJson('/api/social/me/stats')
            ->assertOk()
            ->assertJson([
                'posts_count' => 1,
                'reactions_received' => 1,
                'comments_count' => 1,
            ]);
    }

    public function test_feed_scope_mine_returns_only_own_posts(): void
    {
        $author = $this->makeUser();
        $other = $this->makeUser();

        $this->actingAs($author)->postJson('/api/social/posts', ['content' => 'Của tôi'])->assertCreated();
        $this->actingAs($other)->postJson('/api/social/posts', ['content' => 'Của người khác'])->assertCreated();

        $posts = $this->actingAs($author)
            ->getJson('/api/social/posts?scope=mine')
            ->assertOk()
            ->json('posts');

        $this->assertCount(1, $posts);
        $this->assertSame($author->id, $posts[0]['author']['id']);
    }

    public function test_feed_scope_reacted_returns_posts_user_reacted_to(): void
    {
        $author = $this->makeUser();
        $viewer = $this->makeUser();

        $reactedId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài được thích'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài không thích'])
            ->assertCreated();

        $this->actingAs($viewer)
            ->postJson("/api/social/posts/{$reactedId}/reactions", ['type' => 'love'])
            ->assertOk();

        $posts = $this->actingAs($viewer)
            ->getJson('/api/social/posts?scope=reacted')
            ->assertOk()
            ->json('posts');

        $this->assertCount(1, $posts);
        $this->assertSame($reactedId, $posts[0]['id']);
    }

    public function test_invalid_feed_scope_falls_back_to_all(): void
    {
        $author = $this->makeUser();
        $other = $this->makeUser();

        $this->actingAs($author)->postJson('/api/social/posts', ['content' => 'Của tôi'])->assertCreated();
        $this->actingAs($other)->postJson('/api/social/posts', ['content' => 'Của người khác'])->assertCreated();

        $posts = $this->actingAs($author)
            ->getJson('/api/social/posts?scope=unknown')
            ->assertOk()
            ->json('posts');

        $this->assertCount(2, $posts);
    }
}
