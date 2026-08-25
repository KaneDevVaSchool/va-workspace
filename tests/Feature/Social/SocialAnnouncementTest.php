<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Social\App\Models\SocialPost;
use Tests\TestCase;

class SocialAnnouncementTest extends TestCase
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

    public function test_super_admin_can_post_system_announcement(): void
    {
        $admin = $this->makeUser([], ['super_admin']);

        $this->actingAs($admin)
            ->postJson('/api/social/posts', [
                'content' => 'Bảo trì hệ thống tối nay',
                'as_system_announcement' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('post.pin_scope', 'system')
            ->assertJsonPath('post.is_pinned', true);

        $this->actingAs($admin)
            ->getJson('/api/social/pinned?scope=system')
            ->assertOk()
            ->assertJsonCount(1, 'posts')
            ->assertJsonPath('posts.0.content', fn ($content) => str_contains((string) $content, 'Bảo trì hệ thống tối nay'));

        $this->actingAs($admin)
            ->getJson('/api/social/pinned?scope=company')
            ->assertOk()
            ->assertJsonCount(0, 'posts');
    }

    public function test_member_cannot_post_system_announcement(): void
    {
        $member = $this->makeUser([], ['member']);

        $this->actingAs($member)
            ->postJson('/api/social/posts', [
                'content' => 'Cố đăng thông báo hệ thống',
                'as_system_announcement' => true,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('social_posts', [
            'user_id' => $member->id,
            'pin_scope' => 'system',
        ]);
    }

    public function test_company_pin_does_not_appear_in_system_panel(): void
    {
        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->postJson('/api/social/posts', ['content' => 'Họp toàn công ty'])
            ->assertCreated();

        $post = SocialPost::query()->latest('id')->first();

        $this->actingAs($director)
            ->postJson('/api/social/posts/'.$post->id.'/pin')
            ->assertOk()
            ->assertJsonPath('post.pin_scope', 'company');

        $this->actingAs($director)
            ->getJson('/api/social/pinned?scope=company')
            ->assertOk()
            ->assertJsonCount(1, 'posts');

        $this->actingAs($director)
            ->getJson('/api/social/pinned?scope=system')
            ->assertOk()
            ->assertJsonCount(0, 'posts');
    }

    public function test_pin_activity_log_strips_html_from_post_content(): void
    {
        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->postJson('/api/social/posts', ['content' => '<p>sdfsdfsd</p>'])
            ->assertCreated();

        $post = SocialPost::query()->latest('id')->first();

        $this->actingAs($director)
            ->postJson('/api/social/posts/'.$post->id.'/pin')
            ->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'social_post.pin',
            'description' => 'Ghim bài viết "sdfsdfsd" lên Thông báo công ty',
        ]);
        $this->assertDatabaseMissing('activity_logs', [
            'action' => 'social_post.pin',
            'description' => 'Ghim bài viết "<p>sdfsdfsd</p>" lên Thông báo công ty',
        ]);
    }

    public function test_director_cannot_pin_as_system(): void
    {
        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->postJson('/api/social/posts', ['content' => 'Không được ghim hệ thống'])
            ->assertCreated();

        $post = SocialPost::query()->latest('id')->first();

        $this->actingAs($director)
            ->postJson('/api/social/posts/'.$post->id.'/pin', ['scope' => 'system'])
            ->assertUnprocessable();
    }

    public function test_system_pinned_endpoint_paginates_and_searches(): void
    {
        $admin = $this->makeUser([], ['super_admin']);

        foreach (range(1, 8) as $i) {
            SocialPost::query()->create([
                'user_id' => $admin->id,
                'content' => $i === 4 ? '<p>Mã bảo trì HELIUM-9921 hoàn tất</p>' : "<p>Thông báo hệ thống số {$i}</p>",
                'is_pinned' => true,
                'pin_scope' => 'system',
                'pinned_by' => $admin->id,
                'pinned_at' => now()->subMinutes($i),
            ]);
        }

        $this->actingAs($admin)
            ->getJson('/api/social/pinned?scope=system&per_page=3')
            ->assertOk()
            ->assertJsonCount(3, 'posts')
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 3)
            ->assertJsonPath('total', 8);

        $this->actingAs($admin)
            ->getJson('/api/social/pinned?scope=system&per_page=3&page=3')
            ->assertOk()
            ->assertJsonCount(2, 'posts')
            ->assertJsonPath('current_page', 3)
            ->assertJsonPath('total', 8);

        $this->actingAs($admin)
            ->getJson('/api/social/pinned?scope=system&q=HELIUM-9921')
            ->assertOk()
            ->assertJsonCount(1, 'posts')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('posts.0.content', fn ($content) => str_contains((string) $content, 'HELIUM-9921'));
    }
}
