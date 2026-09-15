<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\UserNotification;
use Tests\TestCase;

class SocialCommentNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name = 'Người dùng'): User
    {
        return User::factory()->create([
            'name' => $name,
            'status' => 'active',
        ]);
    }

    public function test_commenting_on_a_post_notifies_the_post_author(): void
    {
        $author = $this->makeUser('Mai');
        $commenter = $this->makeUser('Bình');

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài viết'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($commenter)
            ->postJson("/api/social/posts/{$postId}/comments", ['content' => 'Bình luận hay quá'])
            ->assertCreated();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $author->id,
            'actor_id' => $commenter->id,
            'type' => 'social_comment',
        ]);

        $list = $this->actingAs($author)
            ->getJson('/api/notifications')
            ->assertOk()
            ->json('notifications');

        $this->assertCount(1, $list);
        $this->assertSame('Bình đã bình luận về bài viết của bạn', $list[0]['title']);
        $this->assertSame('/social?post='.$postId.'&comment='.$list[0]['data']['comment_id'], $list[0]['url']);
    }

    public function test_author_is_not_notified_when_commenting_on_their_own_post(): void
    {
        $author = $this->makeUser('Mai');

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài viết'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($author)
            ->postJson("/api/social/posts/{$postId}/comments", ['content' => 'Tự bình luận'])
            ->assertCreated();

        $this->assertSame(0, UserNotification::query()->count());
    }

    public function test_prior_commenters_are_notified_of_a_new_comment(): void
    {
        $author = $this->makeUser('Mai');
        $first = $this->makeUser('Bình');
        $second = $this->makeUser('Cường');

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài viết'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($first)
            ->postJson("/api/social/posts/{$postId}/comments", ['content' => 'Bình luận 1'])
            ->assertCreated();

        UserNotification::query()->truncate();

        $this->actingAs($second)
            ->postJson("/api/social/posts/{$postId}/comments", ['content' => 'Bình luận 2'])
            ->assertCreated();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $author->id,
            'actor_id' => $second->id,
            'type' => 'social_comment',
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $first->id,
            'actor_id' => $second->id,
            'type' => 'social_comment',
        ]);
    }

    public function test_mentioned_commenter_does_not_receive_a_duplicate_notification(): void
    {
        $author = $this->makeUser('Mai');
        $commenter = $this->makeUser('Bình');

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài viết'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($commenter)
            ->postJson("/api/social/posts/{$postId}/comments", [
                'content' => '<p>Nhắc <span class="mention" data-mention-id="'.$author->id.'">@Mai</span></p>',
            ])
            ->assertCreated();

        $this->assertSame(
            1,
            UserNotification::query()->where('user_id', $author->id)->count(),
        );

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $author->id,
            'actor_id' => $commenter->id,
            'type' => 'mention_comment',
        ]);
    }
}
