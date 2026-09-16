<?php

namespace Tests\Feature\WorkspaceConfig;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\DepartmentSidebarConfig;
use Modules\Identity\App\Models\Role;
use Modules\Identity\App\Models\Team;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Tests\TestCase;

class WorkspaceConfigOverviewTest extends TestCase
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

    public function test_super_admin_overview_includes_inactive_and_config_flags(): void
    {
        $this->seed(RoleSeeder::class);

        $active = Department::query()->create(['code' => 'D1', 'name' => 'Active Dept', 'is_active' => true]);
        $inactive = Department::query()->create(['code' => 'D2', 'name' => 'Inactive Dept', 'is_active' => false]);
        $configuredByTeam = Department::query()->create(['code' => 'D3', 'name' => 'Team Dept', 'is_active' => true]);
        $configuredByMenu = Department::query()->create(['code' => 'D4', 'name' => 'Menu Dept', 'is_active' => true]);

        Team::query()->create(['department_id' => $configuredByTeam->id, 'name' => 'Nhóm A']);
        DepartmentSidebarConfig::query()->create([
            'department_id' => $configuredByMenu->id,
            'menu_key' => 'home',
            'is_visible' => false,
        ]);

        $admin = $this->makeUser([], ['super_admin']);

        $this->actingAs($admin)
            ->getJson('/api/workspace-config/overview')
            ->assertOk()
            ->assertJsonCount(4, 'departments')
            ->assertJsonFragment([
                'id' => $active->id,
                'is_active' => true,
                'has_config' => false,
            ])
            ->assertJsonFragment([
                'id' => $inactive->id,
                'is_active' => false,
                'has_config' => false,
            ])
            ->assertJsonFragment([
                'id' => $configuredByTeam->id,
                'is_active' => true,
                'has_config' => true,
            ])
            ->assertJsonFragment([
                'id' => $configuredByMenu->id,
                'is_active' => true,
                'has_config' => true,
            ]);
    }

    public function test_director_cannot_view_overview(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->getJson('/api/workspace-config/overview')
            ->assertStatus(403);
    }

    public function test_super_admin_lists_unassigned_members(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $assigned = $this->makeUser(['department_id' => $dept->id], ['member']);
        $unassigned = $this->makeUser(['department_id' => null], ['member']);

        $admin = $this->makeUser([], ['super_admin']);

        $response = $this->actingAs($admin)
            ->getJson('/api/workspace-config/members/unassigned')
            ->assertOk()
            ->assertJsonFragment(['id' => $unassigned->id]);

        $ids = collect($response->json('members'))->pluck('id')->all();
        $this->assertNotContains($assigned->id, $ids);
    }

    public function test_super_admin_assigns_department_to_unassigned_member(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $member = $this->makeUser(['department_id' => null], ['member']);
        $admin = $this->makeUser([], ['super_admin']);

        $this->actingAs($admin)
            ->putJson("/api/workspace-config/members/{$member->id}/department", [
                'department_id' => $dept->id,
            ])
            ->assertOk()
            ->assertJsonPath('member.department.id', $dept->id);

        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'department_id' => $dept->id,
        ]);
    }

    public function test_assigning_department_clears_stale_team_from_previous_department(): void
    {
        $this->seed(RoleSeeder::class);

        $oldDept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $newDept = Department::query()->create(['code' => 'D2', 'name' => 'Dept 2', 'is_active' => true]);
        $team = Team::query()->create(['department_id' => $oldDept->id, 'name' => 'Nhóm A']);
        $member = $this->makeUser(['department_id' => $oldDept->id, 'team_id' => $team->id], ['member']);
        $admin = $this->makeUser([], ['super_admin']);

        $this->actingAs($admin)
            ->putJson("/api/workspace-config/members/{$member->id}/department", [
                'department_id' => $newDept->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'department_id' => $newDept->id,
            'team_id' => null,
        ]);
    }

    public function test_director_cannot_assign_department(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $member = $this->makeUser(['department_id' => null], ['member']);

        $this->actingAs($director)
            ->putJson("/api/workspace-config/members/{$member->id}/department", [
                'department_id' => $dept->id,
            ])
            ->assertStatus(403);
    }

    public function test_super_admin_assigns_role_for_any_department(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $member = $this->makeUser(['department_id' => $dept->id], ['member']);
        $admin = $this->makeUser([], ['super_admin']);

        $this->actingAs($admin)
            ->postJson("/api/workspace-config/departments/{$dept->id}/members/roles", [
                'user_id' => $member->id,
                'role_code' => 'team_lead',
            ])
            ->assertOk()
            ->assertJsonPath('member.id', $member->id);

        $this->assertTrue($member->fresh()->roles()->where('code', 'team_lead')->exists());
    }

    public function test_director_cannot_assign_role_via_superadmin_route(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $member = $this->makeUser(['department_id' => $dept->id], ['member']);

        $this->actingAs($director)
            ->postJson("/api/workspace-config/departments/{$dept->id}/members/roles", [
                'user_id' => $member->id,
                'role_code' => 'team_lead',
            ])
            ->assertStatus(403);
    }
}
