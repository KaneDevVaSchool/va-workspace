<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\UserNotification;
use Modules\Social\App\Models\SocialPost;
use Tests\TestCase;

class SocialMentionNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name = 'Người dùng'): User
    {
        return User::factory()->create([
            'name' => $name,
            'status' => 'active',
        ]);
    }

    public function test_mention_span_is_kept_with_user_id(): void
    {
        $author = $this->makeUser('Tác giả');
        $mentioned = $this->makeUser('Nguyễn Văn A');

        $this->actingAs($author)
            ->postJson('/api/social/posts', [
                'content' => '<p>Chào <span class="mention" data-mention-id="'.$mentioned->id.'">@Nguyễn Văn A</span></p>',
            ])
            ->assertCreated();

        $post = SocialPost::query()->latest('id')->first();

        $this->assertStringContainsString('data-mention-id="'.$mentioned->id.'"', (string) $post->content);
        $this->assertStringContainsString('mention', (string) $post->content);
        $this->assertStringContainsString('Nguyễn Văn A', (string) $post->content);
    }

    public function test_mentioning_a_user_in_a_post_creates_a_notification(): void
    {
        $author = $this->makeUser('Mai');
        $mentioned = $this->makeUser('An');

        $response = $this->actingAs($author)
            ->postJson('/api/social/posts', [
                'content' => '<p>Nhắc <span class="mention" data-mention-id="'.$mentioned->id.'">@An</span></p>',
            ])
            ->assertCreated();

        $postId = $response->json('post.id');

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $mentioned->id,
            'actor_id' => $author->id,
            'type' => 'mention_post',
        ]);

        $this->actingAs($mentioned)
            ->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJson(['unread_count' => 1]);

        $list = $this->actingAs($mentioned)
            ->getJson('/api/notifications')
            ->assertOk()
            ->json('notifications');

        $this->assertCount(1, $list);
        $this->assertSame('Mai đã nhắc bạn trong một bài viết', $list[0]['title']);
        $this->assertSame('/social?post='.$postId, $list[0]['url']);
    }

    public function test_author_is_not_notified_when_mentioning_themselves(): void
    {
        $author = $this->makeUser('Mai');

        $this->actingAs($author)
            ->postJson('/api/social/posts', [
                'content' => '<p><span class="mention" data-mention-id="'.$author->id.'">@Mai</span></p>',
            ])
            ->assertCreated();

        $this->assertSame(0, UserNotification::query()->count());
    }

    public function test_mentioning_in_a_comment_notifies_the_user(): void
    {
        $author = $this->makeUser('Mai');
        $mentioned = $this->makeUser('Bình');

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài viết'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($author)
            ->postJson("/api/social/posts/{$postId}/comments", [
                'content' => '<p>Nhắc <span class="mention" data-mention-id="'.$mentioned->id.'">@Bình</span></p>',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $mentioned->id,
            'type' => 'mention_comment',
        ]);
    }

    public function test_mark_notification_as_read(): void
    {
        $author = $this->makeUser('Mai');
        $mentioned = $this->makeUser('An');

        $this->actingAs($author)
            ->postJson('/api/social/posts', [
                'content' => '<p><span class="mention" data-mention-id="'.$mentioned->id.'">@An</span></p>',
            ])
            ->assertCreated();

        $id = UserNotification::query()->where('user_id', $mentioned->id)->value('id');

        $this->actingAs($mentioned)
            ->postJson("/api/notifications/{$id}/read")
            ->assertOk()
            ->assertJson(['unread_count' => 0]);

        $this->assertNotNull(UserNotification::query()->find($id)?->read_at);
    }

    public function test_single_post_can_be_fetched_by_id(): void
    {
        $author = $this->makeUser('Mai');

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài viết'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($author)
            ->getJson("/api/social/posts/{$postId}")
            ->assertOk()
            ->assertJsonPath('post.id', $postId);
    }
}
