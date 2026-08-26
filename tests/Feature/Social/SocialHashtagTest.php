<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Department;
use Modules\Social\App\Models\SocialHashtag;
use Modules\Social\App\Models\SocialPost;
use Tests\TestCase;

class SocialHashtagTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['status' => 'active'], $attributes));
    }

    public function test_posting_extracts_hashtags_and_returns_them(): void
    {
        $author = $this->makeUser();

        $post = $this->actingAs($author)
            ->postJson('/api/social/posts', [
                'content' => '<p>Chào năm học mới #TuyểnSinh #VAS2026 và #tuyểnsinh lần nữa</p>',
            ])
            ->assertCreated()
            ->json('post');

        $names = collect($post['hashtags'])->pluck('name')->sort()->values()->all();
        $this->assertSame(['tuyểnsinh', 'vas2026'], $names);

        $this->assertDatabaseHas('social_hashtags', ['name' => 'tuyểnsinh', 'posts_count' => 1]);
        $this->assertDatabaseHas('social_hashtags', ['name' => 'vas2026', 'posts_count' => 1]);
    }

    public function test_feed_can_filter_by_hashtag(): void
    {
        $author = $this->makeUser();

        $this->actingAs($author)->postJson('/api/social/posts', [
            'content' => '<p>Bài A #hop</p>',
        ])->assertCreated();
        $this->actingAs($author)->postJson('/api/social/posts', [
            'content' => '<p>Bài B #hop #vas</p>',
        ])->assertCreated();
        $this->actingAs($author)->postJson('/api/social/posts', [
            'content' => '<p>Bài C #khac</p>',
        ])->assertCreated();

        $posts = $this->actingAs($author)
            ->getJson('/api/social/posts?hashtag=hop')
            ->assertOk()
            ->json('posts');

        $this->assertCount(2, $posts);
        $this->assertSame(2, $this->actingAs($author)->getJson('/api/social/posts?hashtag=hop')->json('total'));
    }

    public function test_recent_hashtags_include_usage_count_in_visible_feed(): void
    {
        $author = $this->makeUser();

        $this->actingAs($author)->postJson('/api/social/posts', [
            'content' => '<p>#hop một</p>',
        ])->assertCreated();
        $this->actingAs($author)->postJson('/api/social/posts', [
            'content' => '<p>#hop hai</p>',
        ])->assertCreated();
        $this->actingAs($author)->postJson('/api/social/posts', [
            'content' => '<p>#vas</p>',
        ])->assertCreated();

        $tags = $this->actingAs($author)
            ->getJson('/api/social/hashtags')
            ->assertOk()
            ->json('hashtags');

        $byName = collect($tags)->keyBy('name');
        $this->assertSame(2, $byName['hop']['usage_count']);
        $this->assertSame(1, $byName['vas']['usage_count']);
        $this->assertSame('vas', $tags[0]['name']);
    }

    public function test_editing_post_resyncs_hashtags_and_deletes_unused(): void
    {
        $author = $this->makeUser();

        $id = $this->actingAs($author)
            ->postJson('/api/social/posts', [
                'content' => '<p>#cu #giu</p>',
            ])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($author)
            ->putJson('/api/social/posts/'.$id, [
                'content' => '<p>#giu #moi</p>',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('social_hashtags', ['name' => 'cu']);
        $this->assertDatabaseHas('social_hashtags', ['name' => 'giu', 'posts_count' => 1]);
        $this->assertDatabaseHas('social_hashtags', ['name' => 'moi', 'posts_count' => 1]);
    }

    public function test_hashtag_count_hides_restricted_department_posts(): void
    {
        $deptA = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $deptB = Department::query()->create(['code' => 'B', 'name' => 'Phòng B', 'is_active' => true]);
        $author = $this->makeUser(['department_id' => $deptA->id]);
        $viewer = $this->makeUser(['department_id' => $deptB->id]);

        $this->actingAs($author)
            ->postJson('/api/social/posts', [
                'content' => '<p>Nội bộ #mat</p>',
                'department_visibility_mode' => 'include',
                'department_visibility_ids' => [$deptA->id],
            ])
            ->assertCreated();

        $tags = $this->actingAs($viewer)
            ->getJson('/api/social/hashtags')
            ->assertOk()
            ->json('hashtags');

        $this->assertFalse(collect($tags)->contains('name', 'mat'));
    }

    public function test_does_not_extract_url_fragments_as_hashtags(): void
    {
        $author = $this->makeUser();

        $this->actingAs($author)
            ->postJson('/api/social/posts', [
                'content' => '<p>Xem https://vaschools.edu.vn/#gioithieu và #sukien</p>',
            ])
            ->assertCreated();

        $this->assertDatabaseMissing('social_hashtags', ['name' => 'gioithieu']);
        $this->assertDatabaseHas('social_hashtags', ['name' => 'sukien']);
    }

    public function test_deleting_post_removes_orphan_hashtag(): void
    {
        $author = $this->makeUser();

        $id = $this->actingAs($author)
            ->postJson('/api/social/posts', [
                'content' => '<p>#tamthoi</p>',
            ])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($author)
            ->deleteJson('/api/social/posts/'.$id)
            ->assertOk();

        $this->assertDatabaseMissing('social_hashtags', ['name' => 'tamthoi']);
        $this->assertSame(0, SocialPost::query()->count());
        $this->assertSame(0, SocialHashtag::query()->count());
    }
}
