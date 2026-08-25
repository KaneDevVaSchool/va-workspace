<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Social\App\Models\SocialGroup;
use Modules\Social\App\Models\SocialGroupJoinRequest;
use Modules\Social\App\Models\SocialGroupMember;
use Tests\TestCase;

class SocialGroupPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function makeUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));
        $roleIds = Role::query()->where('code', 'member')->pluck('id');
        if ($roleIds->isNotEmpty()) {
            $user->roles()->sync($roleIds);
            $user->unsetRelation('roles');
        }

        return $user;
    }

    private function createGroup(User $owner, string $visibility = 'private'): SocialGroup
    {
        $this->actingAs($owner)
            ->postJson('/api/social/groups', [
                'name' => $visibility === 'private' ? 'Nhóm bảo mật Toán' : 'Nhóm công khai',
                'description' => 'Trao đổi chuyên môn',
                'visibility' => $visibility,
            ])
            ->assertCreated();

        return SocialGroup::query()->latest('id')->firstOrFail();
    }

    public function test_outsider_cannot_view_private_group_posts_or_members(): void
    {
        $owner = $this->makeUser();
        $outsider = $this->makeUser();
        $group = $this->createGroup($owner, 'private');

        $this->actingAs($outsider)
            ->getJson('/api/social/groups/'.$group->id)
            ->assertOk()
            ->assertJsonPath('group.is_member', false)
            ->assertJsonMissingPath('group.creator');

        $this->actingAs($outsider)
            ->getJson('/api/social/posts?post_scope=group&group_id='.$group->id)
            ->assertForbidden();

        $this->actingAs($outsider)
            ->getJson('/api/social/groups/'.$group->id.'/members')
            ->assertStatus(422);
    }

    public function test_joining_private_group_creates_request_for_admin_to_approve(): void
    {
        $owner = $this->makeUser();
        $applicant = $this->makeUser();
        $group = $this->createGroup($owner, 'private');

        $this->actingAs($applicant)
            ->postJson('/api/social/groups/'.$group->id.'/join')
            ->assertOk()
            ->assertJsonPath('status', 'requested')
            ->assertJsonPath('group.is_member', false)
            ->assertJsonPath('group.has_pending_request', true);

        $this->assertDatabaseHas('social_group_members', [
            'group_id' => $group->id,
            'user_id' => $owner->id,
        ]);
        $this->assertDatabaseMissing('social_group_members', [
            'group_id' => $group->id,
            'user_id' => $applicant->id,
        ]);

        $requestId = SocialGroupJoinRequest::query()
            ->where('group_id', $group->id)
            ->where('user_id', $applicant->id)
            ->value('id');

        $this->actingAs($owner)
            ->postJson('/api/social/groups/'.$group->id.'/requests/'.$requestId.'/approve')
            ->assertOk();

        $this->assertDatabaseHas('social_group_members', [
            'group_id' => $group->id,
            'user_id' => $applicant->id,
            'role' => SocialGroupMember::ROLE_MEMBER,
        ]);
    }

    public function test_inviting_to_private_group_requires_invitee_to_accept(): void
    {
        $owner = $this->makeUser();
        $invitee = $this->makeUser(['name' => 'Nguyen Van B']);
        $group = $this->createGroup($owner, 'private');

        $this->actingAs($owner)
            ->postJson('/api/social/groups/'.$group->id.'/invites', ['user_id' => $invitee->id])
            ->assertCreated()
            ->assertJsonPath('status', 'invited');

        $this->assertDatabaseMissing('social_group_members', [
            'group_id' => $group->id,
            'user_id' => $invitee->id,
        ]);

        $invite = SocialGroupJoinRequest::query()
            ->where('group_id', $group->id)
            ->where('user_id', $invitee->id)
            ->where('kind', SocialGroupJoinRequest::KIND_INVITE)
            ->firstOrFail();

        $this->actingAs($owner)
            ->postJson('/api/social/groups/'.$group->id.'/requests/'.$invite->id.'/approve')
            ->assertStatus(422);

        $this->actingAs($invitee)
            ->postJson('/api/social/groups/invites/'.$invite->id.'/accept')
            ->assertOk()
            ->assertJsonPath('group.is_member', true);

        $this->assertDatabaseHas('social_group_members', [
            'group_id' => $group->id,
            'user_id' => $invitee->id,
        ]);
    }

    public function test_public_group_join_is_immediate(): void
    {
        $owner = $this->makeUser();
        $member = $this->makeUser();
        $group = $this->createGroup($owner, 'public');

        $this->actingAs($member)
            ->postJson('/api/social/groups/'.$group->id.'/join')
            ->assertOk()
            ->assertJsonPath('status', 'joined')
            ->assertJsonPath('group.is_member', true);
    }

    public function test_member_can_share_company_post_to_group(): void
    {
        $owner = $this->makeUser();
        $outsider = $this->makeUser();
        $group = $this->createGroup($owner, 'public');

        $postId = $this->actingAs($owner)
            ->postJson('/api/social/posts', ['content' => 'Bài gốc bảng tin'])
            ->assertCreated()
            ->json('post.id');

        $this->actingAs($owner)
            ->postJson("/api/social/posts/{$postId}/share", [
                'post_scope' => 'group',
                'group_id' => $group->id,
                'caption' => 'Chia sẻ vào nhóm',
            ])
            ->assertCreated()
            ->assertJsonPath('post.post_scope', 'group')
            ->assertJsonPath('post.group.id', $group->id)
            ->assertJsonPath('post.shared_from.id', $postId);

        $this->actingAs($outsider)
            ->postJson("/api/social/posts/{$postId}/share", [
                'post_scope' => 'group',
                'group_id' => $group->id,
            ])
            ->assertStatus(422);
    }
}
