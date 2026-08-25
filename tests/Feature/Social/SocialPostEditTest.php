<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Social\App\Models\SocialPost;
use Tests\TestCase;

class SocialPostEditTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    public function test_author_can_update_own_post(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/social/posts', ['content' => 'Bài viết ban đầu'])
            ->assertCreated();

        $post = SocialPost::query()->latest('id')->first();

        $this->actingAs($user)
            ->putJson('/api/social/posts/'.$post->id, ['content' => 'Bài viết đã sửa'])
            ->assertOk()
            ->assertJsonPath('post.content', fn ($content) => str_contains((string) $content, 'Bài viết đã sửa'));

        $this->assertStringContainsString('Bài viết đã sửa', $post->fresh()->content);
        $this->assertStringNotContainsString('Bài viết ban đầu', $post->fresh()->content);
    }

    public function test_other_user_cannot_update_post(): void
    {
        $author = $this->makeUser();
        $other = $this->makeUser();

        $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Không được sửa hộ'])
            ->assertCreated();

        $post = SocialPost::query()->latest('id')->first();

        $this->actingAs($other)
            ->putJson('/api/social/posts/'.$post->id, ['content' => 'Sửa trái phép'])
            ->assertUnprocessable();

        $this->assertStringContainsString('Không được sửa hộ', $post->fresh()->content);
    }

    public function test_update_stores_previous_version_in_revision_history(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/social/posts', ['content' => 'Bản gốc'])
            ->assertCreated();

        $post = SocialPost::query()->latest('id')->first();

        $this->actingAs($user)
            ->putJson('/api/social/posts/'.$post->id, ['content' => 'Bản sửa lần 1'])
            ->assertOk()
            ->assertJsonPath('post.is_edited', true);

        $this->actingAs($user)
            ->getJson('/api/social/posts/'.$post->id.'/revisions')
            ->assertOk()
            ->assertJsonCount(2, 'versions')
            ->assertJsonPath('versions.0.is_current', true)
            ->assertJsonPath('versions.0.content', fn ($content) => str_contains((string) $content, 'Bản sửa lần 1'))
            ->assertJsonPath('versions.1.is_current', false)
            ->assertJsonPath('versions.1.content', fn ($content) => str_contains((string) $content, 'Bản gốc'));
    }
}
