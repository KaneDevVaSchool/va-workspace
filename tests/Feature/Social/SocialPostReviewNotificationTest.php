<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Social\App\Models\SocialPost;
use Tests\TestCase;

class SocialPostReviewNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function makeUser(array $attributes = [], array $roles = []): User
    {
        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));

        if ($roles !== []) {
            $roleIds = Role::query()->whereIn('code', $roles)->pluck('id');
            $user->roles()->sync($roleIds);
            $user->unsetRelation('roles');
        }

        return $user;
    }

    public function test_approving_a_post_notifies_its_author(): void
    {
        $author = $this->makeUser();
        $reviewer = $this->makeUser([], ['super_admin']);

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài chờ duyệt'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($reviewer)
            ->postJson("/api/social/moderation/{$postId}/approve")
            ->assertOk();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $author->id,
            'actor_id' => $reviewer->id,
            'type' => 'social_post_approved',
        ]);
    }

    public function test_rejecting_a_post_notifies_its_author_with_reason(): void
    {
        $author = $this->makeUser();
        $reviewer = $this->makeUser([], ['super_admin']);

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài chờ duyệt khác'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($reviewer)
            ->postJson("/api/social/moderation/{$postId}/reject", ['reason' => 'Nội dung không phù hợp'])
            ->assertOk();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $author->id,
            'actor_id' => $reviewer->id,
            'type' => 'social_post_rejected',
            'body' => 'Nội dung không phù hợp',
        ]);

        $post = SocialPost::query()->find($postId);
        $this->assertSame(SocialPost::REVIEW_REJECTED, $post->review_status);
    }
}
