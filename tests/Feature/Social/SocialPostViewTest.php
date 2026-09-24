<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Social\App\Models\SocialPost;
use Tests\TestCase;

class SocialPostViewTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name = 'Người dùng'): User
    {
        return User::factory()->create([
            'name' => $name,
            'status' => 'active',
        ]);
    }

    public function test_feed_includes_views_count(): void
    {
        $author = $this->makeUser('Tác giả');

        $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài viết'])
            ->assertCreated()
            ->assertJsonPath('post.views_count', 0)
            ->assertJsonPath('post.viewed', false);
    }

    public function test_viewer_records_unique_view(): void
    {
        $author = $this->makeUser('Tác giả');
        $viewer = $this->makeUser('Người xem');

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài viết'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($viewer)
            ->postJson("/api/social/posts/{$postId}/view")
            ->assertOk()
            ->assertJson([
                'views_count' => 1,
                'viewed' => true,
                'recorded' => true,
            ]);

        $this->actingAs($viewer)
            ->postJson("/api/social/posts/{$postId}/view")
            ->assertOk()
            ->assertJson([
                'views_count' => 1,
                'viewed' => true,
                'recorded' => false,
            ]);

        $this->actingAs($author)
            ->getJson("/api/social/posts/{$postId}")
            ->assertOk()
            ->assertJsonPath('post.views_count', 1)
            ->assertJsonPath('post.viewed', false);
    }

    public function test_author_view_is_not_counted(): void
    {
        $author = $this->makeUser('Tác giả');

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài viết'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($author)
            ->postJson("/api/social/posts/{$postId}/view")
            ->assertOk()
            ->assertJson([
                'views_count' => 0,
                'viewed' => false,
                'recorded' => false,
            ]);
    }

    public function test_two_viewers_increment_count(): void
    {
        $author = $this->makeUser('Tác giả');
        $first = $this->makeUser('Người xem 1');
        $second = $this->makeUser('Người xem 2');

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài viết'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($first)
            ->postJson("/api/social/posts/{$postId}/view")
            ->assertOk();

        $this->actingAs($second)
            ->postJson("/api/social/posts/{$postId}/view")
            ->assertOk()
            ->assertJsonPath('views_count', 2);
    }

    public function test_feed_search_matches_content_or_author_name(): void
    {
        $author = $this->makeUser('Lan Phương');
        $other = $this->makeUser('Minh');

        $matchId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Lịch họp phòng đào tạo'])
            ->json('post.id');
        $nameId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Nhắc việc thường'])
            ->json('post.id');
        $missId = $this->actingAs($other)
            ->postJson('/api/social/posts', ['content' => 'Việc khác'])
            ->json('post.id');

        SocialPost::query()->whereIn('id', [$matchId, $nameId, $missId])->update([
            'review_status' => SocialPost::REVIEW_APPROVED,
        ]);

        $byContent = $this->actingAs($other)
            ->getJson('/api/social/posts?q=phòng đào tạo')
            ->assertOk()
            ->json('posts');
        $this->assertSame([$matchId], array_column($byContent, 'id'));

        $byAuthor = $this->actingAs($other)
            ->getJson('/api/social/posts?q=Lan')
            ->assertOk()
            ->json('posts');
        $ids = array_column($byAuthor, 'id');
        sort($ids);
        $expected = [$matchId, $nameId];
        sort($expected);
        $this->assertSame($expected, $ids);
    }
}
