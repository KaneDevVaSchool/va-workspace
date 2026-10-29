<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Social\App\Models\SocialPost;
use Tests\TestCase;

class SocialPostModerationQueueTest extends TestCase
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

    private function createPendingPost(User $author, string $content): int
    {
        return $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => $content])
            ->assertCreated()
            ->json('post.id');
    }

    public function test_rejected_posts_are_saved_and_listed_separately_from_pending(): void
    {
        $author = $this->makeUser();
        $reviewer = $this->makeUser([], ['super_admin']);

        $pendingId = $this->createPendingPost($author, 'Bài còn chờ');
        $rejectedId = $this->createPendingPost($author, 'Bài sẽ bị từ chối');

        $this->actingAs($reviewer)
            ->postJson("/api/social/moderation/{$rejectedId}/reject", ['reason' => 'Chưa phù hợp'])
            ->assertOk();

        $pending = $this->actingAs($reviewer)
            ->getJson('/api/social/moderation')
            ->assertOk()
            ->json();

        $this->assertSame('pending', $pending['status']);
        $this->assertSame(1, $pending['pending_count']);
        $this->assertSame(1, $pending['rejected_count']);
        $this->assertSame([$pendingId], collect($pending['posts'])->pluck('id')->all());

        $rejected = $this->actingAs($reviewer)
            ->getJson('/api/social/moderation?status=rejected')
            ->assertOk()
            ->json();

        $this->assertSame('rejected', $rejected['status']);
        $this->assertSame(1, $rejected['total']);
        $this->assertSame($rejectedId, $rejected['posts'][0]['id']);
        $this->assertSame('rejected', $rejected['posts'][0]['review_status']);
        $this->assertSame('Chưa phù hợp', $rejected['posts'][0]['review_reject_reason']);
        $this->assertSame($reviewer->name, $rejected['posts'][0]['reviewed_by']);
        $this->assertNotNull($rejected['posts'][0]['reviewed_at']);

        $this->assertDatabaseHas('social_posts', [
            'id' => $rejectedId,
            'review_status' => SocialPost::REVIEW_REJECTED,
            'review_reject_reason' => 'Chưa phù hợp',
        ]);
    }

    public function test_rejected_post_can_be_approved_again(): void
    {
        $author = $this->makeUser();
        $reviewer = $this->makeUser([], ['super_admin']);
        $postId = $this->createPendingPost($author, 'Bài xem xét lại');

        $this->actingAs($reviewer)
            ->postJson("/api/social/moderation/{$postId}/reject", ['reason' => 'Cần chỉnh'])
            ->assertOk();

        $this->actingAs($reviewer)
            ->postJson("/api/social/moderation/{$postId}/approve")
            ->assertOk()
            ->assertJsonPath('post.review_status', 'approved');

        $this->assertDatabaseHas('social_posts', [
            'id' => $postId,
            'review_status' => SocialPost::REVIEW_APPROVED,
            'review_reject_reason' => null,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $author->id,
            'actor_id' => $reviewer->id,
            'type' => 'social_post_approved',
            'title' => 'Bài viết của bạn đã được duyệt lại',
        ]);

        $this->actingAs($reviewer)
            ->getJson('/api/social/moderation?status=rejected')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('rejected_count', 0);
    }

    public function test_manually_approved_posts_are_listed_separately(): void
    {
        $author = $this->makeUser();
        $reviewer = $this->makeUser([], ['super_admin']);

        $pendingId = $this->createPendingPost($author, 'Bài sẽ duyệt');
        $this->createPendingPost($author, 'Bài vẫn chờ');

        $this->actingAs($reviewer)
            ->postJson("/api/social/moderation/{$pendingId}/approve")
            ->assertOk();

        $approved = $this->actingAs($reviewer)
            ->getJson('/api/social/moderation?status=approved')
            ->assertOk()
            ->json();

        $this->assertSame('approved', $approved['status']);
        $this->assertSame(1, $approved['total']);
        $this->assertSame(1, $approved['approved_count']);
        $this->assertSame(1, $approved['pending_count']);
        $this->assertSame($pendingId, $approved['posts'][0]['id']);
        $this->assertSame('approved', $approved['posts'][0]['review_status']);
        $this->assertSame($reviewer->name, $approved['posts'][0]['reviewed_by']);
    }

    public function test_approved_post_can_be_rejected_to_hide_from_feed(): void
    {
        $author = $this->makeUser();
        $reviewer = $this->makeUser([], ['super_admin']);
        $postId = $this->createPendingPost($author, 'Bài đang công khai');

        $this->actingAs($reviewer)
            ->postJson("/api/social/moderation/{$postId}/approve")
            ->assertOk();

        $this->actingAs($reviewer)
            ->postJson("/api/social/moderation/{$postId}/reject", ['reason' => 'Cần chỉnh sau'])
            ->assertOk()
            ->assertJsonPath('post.review_status', 'rejected');

        $this->actingAs($reviewer)
            ->getJson('/api/social/moderation?status=approved')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('approved_count', 0)
            ->assertJsonPath('rejected_count', 1);
    }

    public function test_already_rejected_post_cannot_be_rejected_again(): void
    {
        $author = $this->makeUser();
        $reviewer = $this->makeUser([], ['super_admin']);
        $postId = $this->createPendingPost($author, 'Bài từ chối một lần');

        $this->actingAs($reviewer)
            ->postJson("/api/social/moderation/{$postId}/reject", ['reason' => 'Lần một'])
            ->assertOk();

        $this->actingAs($reviewer)
            ->postJson("/api/social/moderation/{$postId}/reject", ['reason' => 'Lần hai'])
            ->assertNotFound();

        $this->assertDatabaseHas('social_posts', [
            'id' => $postId,
            'review_status' => SocialPost::REVIEW_REJECTED,
            'review_reject_reason' => 'Lần một',
        ]);
    }
}
