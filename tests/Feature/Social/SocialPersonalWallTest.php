<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Social\App\Models\SocialPost;
use Tests\TestCase;

class SocialPersonalWallTest extends TestCase
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

    public function test_personal_post_does_not_appear_in_company_or_department_feed(): void
    {
        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Phòng Công nghệ', 'is_active' => true]);
        $user = $this->makeUser(['department_id' => $dept->id]);

        $this->actingAs($user)
            ->postJson('/api/social/posts', [
                'content' => 'Bài trên tường của tôi',
                'post_scope' => 'personal',
            ])
            ->assertCreated()
            ->assertJsonPath('post.post_scope', 'personal')
            ->assertJsonPath('post.wall_user.id', $user->id);

        $this->actingAs($user)
            ->getJson('/api/social/posts?post_scope=company')
            ->assertOk()
            ->assertJsonCount(0, 'posts');

        $this->actingAs($user)
            ->getJson('/api/social/posts?post_scope=department')
            ->assertOk()
            ->assertJsonCount(0, 'posts');

        $personal = $this->actingAs($user)
            ->getJson('/api/social/posts?post_scope=personal')
            ->assertOk()
            ->json('posts');

        $this->assertCount(1, $personal);
        $this->assertSame('personal', $personal[0]['post_scope']);
    }

    public function test_can_post_on_another_users_wall(): void
    {
        $owner = $this->makeUser();
        $visitor = $this->makeUser();

        $this->actingAs($visitor)
            ->postJson('/api/social/posts', [
                'content' => 'Chúc mừng đồng nghiệp',
                'post_scope' => 'personal',
                'wall_user_id' => $owner->id,
            ])
            ->assertCreated()
            ->assertJsonPath('post.author.id', $visitor->id)
            ->assertJsonPath('post.wall_user.id', $owner->id);

        $this->actingAs($owner)
            ->getJson('/api/social/posts?post_scope=personal&wall_user_id='.$owner->id)
            ->assertOk()
            ->assertJsonCount(1, 'posts')
            ->assertJsonPath('posts.0.author.id', $visitor->id);

        $this->actingAs($visitor)
            ->getJson('/api/social/posts?post_scope=personal&wall_user_id='.$visitor->id)
            ->assertOk()
            ->assertJsonCount(0, 'posts');
    }

    public function test_share_to_department_personal_and_other_wall(): void
    {
        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Phòng Công nghệ', 'is_active' => true]);
        $author = $this->makeUser(['department_id' => $dept->id]);
        $other = $this->makeUser(['department_id' => $dept->id]);

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài gốc'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($author)
            ->postJson("/api/social/posts/{$postId}/share", ['post_scope' => 'department'])
            ->assertCreated()
            ->assertJsonPath('post.post_scope', 'department');

        $this->actingAs($author)
            ->postJson("/api/social/posts/{$postId}/share", ['post_scope' => 'personal'])
            ->assertCreated()
            ->assertJsonPath('post.post_scope', 'personal')
            ->assertJsonPath('post.wall_user.id', $author->id);

        $this->actingAs($author)
            ->postJson("/api/social/posts/{$postId}/share", [
                'post_scope' => 'personal',
                'wall_user_id' => $other->id,
                'caption' => 'Xem bài này nhé',
            ])
            ->assertCreated()
            ->assertJsonPath('post.post_scope', 'personal')
            ->assertJsonPath('post.wall_user.id', $other->id)
            ->assertJsonPath('post.shared_from.id', $postId);

        $this->actingAs($other)
            ->getJson('/api/social/posts?post_scope=personal&wall_user_id='.$other->id)
            ->assertOk()
            ->assertJsonCount(1, 'posts');
    }

    public function test_company_pin_does_not_appear_on_department_pinned_panel(): void
    {
        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Phòng Công nghệ', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->postJson('/api/social/posts', ['content' => 'Ghim bảng tin chung'])
            ->assertCreated();

        $post = SocialPost::query()->latest('id')->first();

        $this->actingAs($director)
            ->postJson('/api/social/posts/'.$post->id.'/pin')
            ->assertOk()
            ->assertJsonPath('post.pin_scope', 'company')
            ->assertJsonPath('post.post_scope', 'company');

        $this->actingAs($director)
            ->getJson('/api/social/pinned?scope=company&post_scope=company')
            ->assertOk()
            ->assertJsonCount(1, 'posts');

        $this->actingAs($director)
            ->getJson('/api/social/pinned?scope=company&post_scope=department')
            ->assertOk()
            ->assertJsonCount(0, 'posts');

        $this->actingAs($director)
            ->getJson('/api/social/posts?post_scope=department')
            ->assertOk()
            ->assertJsonCount(0, 'posts');
    }

    public function test_department_pin_does_not_appear_on_company_pinned_panel(): void
    {
        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Phòng Công nghệ', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->postJson('/api/social/posts', [
                'content' => 'Ghim tường phòng',
                'post_scope' => 'department',
            ])
            ->assertCreated();

        $post = SocialPost::query()->latest('id')->first();

        $this->actingAs($director)
            ->postJson('/api/social/posts/'.$post->id.'/pin')
            ->assertOk()
            ->assertJsonPath('post.post_scope', 'department');

        $this->actingAs($director)
            ->getJson('/api/social/pinned?scope=company&post_scope=department')
            ->assertOk()
            ->assertJsonCount(1, 'posts');

        $this->actingAs($director)
            ->getJson('/api/social/pinned?scope=company&post_scope=company')
            ->assertOk()
            ->assertJsonCount(0, 'posts');
    }

    public function test_wall_profile_endpoint(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        $this->actingAs($other)
            ->getJson('/api/social/walls/'.$user->id)
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('is_own', false)
            ->assertJsonPath('user.email', null);

        $this->actingAs($user)
            ->getJson('/api/social/walls/'.$user->id)
            ->assertOk()
            ->assertJsonPath('is_own', true)
            ->assertJsonPath('user.email', $user->email);
    }
}
