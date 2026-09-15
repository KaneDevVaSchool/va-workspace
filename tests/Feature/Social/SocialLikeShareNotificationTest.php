<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\UserNotification;
use Tests\TestCase;

class SocialLikeShareNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name = 'Người dùng'): User
    {
        return User::factory()->create([
            'name' => $name,
            'status' => 'active',
        ]);
    }

    public function test_liking_a_post_notifies_its_author(): void
    {
        $author = $this->makeUser('Mai');
        $liker = $this->makeUser('Bình');

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài viết'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($liker)
            ->postJson("/api/social/posts/{$postId}/reactions", ['type' => 'like'])
            ->assertOk();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $author->id,
            'actor_id' => $liker->id,
            'type' => 'like_post',
        ]);
    }

    public function test_author_is_not_notified_when_liking_their_own_post(): void
    {
        $author = $this->makeUser('Mai');

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài viết'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($author)
            ->postJson("/api/social/posts/{$postId}/reactions", ['type' => 'like'])
            ->assertOk();

        $this->assertSame(0, UserNotification::query()->count());
    }

    public function test_unliking_a_post_does_not_create_a_notification(): void
    {
        $author = $this->makeUser('Mai');
        $liker = $this->makeUser('Bình');

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài viết'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($liker)
            ->postJson("/api/social/posts/{$postId}/reactions", ['type' => 'like'])
            ->assertOk();

        UserNotification::query()->truncate();

        $this->actingAs($liker)
            ->postJson("/api/social/posts/{$postId}/reactions", ['type' => 'like'])
            ->assertOk();

        $this->assertSame(0, UserNotification::query()->count());
    }

    public function test_liking_a_comment_notifies_its_author(): void
    {
        $postAuthor = $this->makeUser('Mai');
        $commenter = $this->makeUser('Bình');
        $liker = $this->makeUser('Cường');

        $postId = $this->actingAs($postAuthor)
            ->postJson('/api/social/posts', ['content' => 'Bài viết'])
            ->assertCreated()
            ->json('post.id');

        $commentId = $this->actingAs($commenter)
            ->postJson("/api/social/posts/{$postId}/comments", ['content' => 'Bình luận'])
            ->assertCreated()
            ->json('comment.id');

        UserNotification::query()->truncate();

        $this->actingAs($liker)
            ->postJson("/api/social/comments/{$commentId}/reactions", ['type' => 'like'])
            ->assertOk();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $commenter->id,
            'actor_id' => $liker->id,
            'type' => 'like_comment',
        ]);
    }

    public function test_sharing_a_post_notifies_the_original_author(): void
    {
        $author = $this->makeUser('Mai');
        $sharer = $this->makeUser('Bình');

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài viết'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($sharer)
            ->postJson("/api/social/posts/{$postId}/share", ['caption' => 'Chia sẻ nè'])
            ->assertCreated();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $author->id,
            'actor_id' => $sharer->id,
            'type' => 'share_post',
        ]);
    }

    public function test_author_is_not_notified_when_sharing_their_own_post(): void
    {
        $author = $this->makeUser('Mai');

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài viết'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($author)
            ->postJson("/api/social/posts/{$postId}/share", ['caption' => null])
            ->assertCreated();

        $this->assertSame(0, UserNotification::query()->count());
    }
}
