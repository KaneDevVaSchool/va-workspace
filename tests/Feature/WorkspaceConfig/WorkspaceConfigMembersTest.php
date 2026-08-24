<?php

namespace Tests\Feature\WorkspaceConfig;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\App\Models\Team;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Tests\TestCase;

class WorkspaceConfigMembersTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = [], array $roles = []): User
    {
        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));

        if ($roles !== []) {
            $roleIds = Role::query()->whereIn('code', $roles)->pluck('id');
            $user->roles()->sync($roleIds);
        }

        return $user;
    }

    public function test_director_can_assign_department_role_to_member(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $member = $this->makeUser(['department_id' => $dept->id], ['member']);

        $this->actingAs($director)
            ->postJson('/api/workspace-config/members/roles', [
                'user_id' => $member->id,
                'role_code' => 'team_lead',
            ])
            ->assertOk()
            ->assertJsonPath('member.id', $member->id)
            ->assertJsonPath('member.roles.0.code', 'team_lead');

        $this->assertTrue($member->fresh()->hasRole('team_lead'));
        $this->assertFalse($member->fresh()->hasRole('member'));
    }

    public function test_cannot_assign_protected_or_own_role(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $otherDirector = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $member = $this->makeUser(['department_id' => $dept->id], ['member']);

        $this->actingAs($director)
            ->postJson('/api/workspace-config/members/roles', [
                'user_id' => $director->id,
                'role_code' => 'member',
            ])
            ->assertStatus(422);

        $this->actingAs($director)
            ->postJson('/api/workspace-config/members/roles', [
                'user_id' => $otherDirector->id,
                'role_code' => 'member',
            ])
            ->assertStatus(422);

        $this->actingAs($director)
            ->postJson('/api/workspace-config/members/roles', [
                'user_id' => $member->id,
                'role_code' => 'super_admin',
            ])
            ->assertStatus(422);
    }

    public function test_member_without_permission_cannot_assign_role(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $actor = $this->makeUser(['department_id' => $dept->id], ['member']);
        $target = $this->makeUser(['department_id' => $dept->id], ['member']);

        $this->actingAs($actor)
            ->postJson('/api/workspace-config/members/roles', [
                'user_id' => $target->id,
                'role_code' => 'team_lead',
            ])
            ->assertStatus(403);
    }

    public function test_members_index_includes_assignable_roles(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->getJson('/api/workspace-config/members')
            ->assertOk()
            ->assertJsonPath('assignable_roles.0.code', 'deputy_department_director')
            ->assertJsonFragment(['code' => 'team_lead'])
            ->assertJsonMissing(['code' => 'super_admin']);
    }

    public function test_members_index_requires_department(): void
    {
        $this->seed(RoleSeeder::class);

        $director = $this->makeUser(['department_id' => null], ['department_director']);

        $this->actingAs($director)
            ->getJson('/api/workspace-config/members')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tài khoản chưa gắn với phòng ban nào.');
    }

    public function test_director_can_update_team_name_and_lead(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $lead = $this->makeUser(['department_id' => $dept->id], ['member']);
        $team = Team::query()->create(['department_id' => $dept->id, 'name' => 'Nhóm A']);

        $this->actingAs($director)
            ->putJson('/api/workspace-config/members/teams/'.$team->id, [
                'name' => 'Nhóm B',
                'team_lead_id' => $lead->id,
            ])
            ->assertOk()
            ->assertJsonPath('team.name', 'Nhóm B')
            ->assertJsonPath('team.team_lead.id', $lead->id);

        $this->assertSame('Nhóm B', $team->fresh()->name);
        $this->assertSame($lead->id, $team->fresh()->team_lead_id);
    }

    public function test_cannot_update_team_of_another_department(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $other = Department::query()->create(['code' => 'D2', 'name' => 'Dept 2', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $team = Team::query()->create(['department_id' => $other->id, 'name' => 'Nhóm khác']);

        $this->actingAs($director)
            ->putJson('/api/workspace-config/members/teams/'.$team->id, [
                'name' => 'Không được',
            ])
            ->assertStatus(404);

        $this->assertSame('Nhóm khác', $team->fresh()->name);
    }

    public function test_member_without_permission_cannot_update_team(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $actor = $this->makeUser(['department_id' => $dept->id], ['member']);
        $team = Team::query()->create(['department_id' => $dept->id, 'name' => 'Nhóm A']);

        $this->actingAs($actor)
            ->putJson('/api/workspace-config/members/teams/'.$team->id, [
                'name' => 'Nhóm B',
            ])
            ->assertStatus(403);
    }
}
