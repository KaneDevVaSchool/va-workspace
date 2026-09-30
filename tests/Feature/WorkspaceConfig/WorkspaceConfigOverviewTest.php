<?php

namespace Tests\Feature\WorkspaceConfig;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Identity\App\Models\Company;
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

    public function test_overview_includes_company_and_director(): void
    {
        $this->seed(RoleSeeder::class);

        $company = Company::query()->create([
            'hrm_uuid' => 'co-test-1',
            'code' => 'VAS',
            'name' => 'VA Schools',
            'is_active' => true,
        ]);
        $dept = Department::query()->create([
            'code' => 'D1',
            'name' => 'Dept 1',
            'is_active' => true,
            'company_id' => $company->id,
        ]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $admin = $this->makeUser([], ['super_admin']);

        $this->actingAs($admin)
            ->getJson('/api/workspace-config/overview')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $dept->id,
                'company' => [
                    'id' => $company->id,
                    'code' => 'VAS',
                    'name' => 'VA Schools',
                ],
                'director' => [
                    'id' => $director->id,
                    'name' => $director->name,
                    'email' => $director->email,
                    'avatar_url' => $director->avatar_url,
                ],
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

    public function test_super_admin_lists_members_grouped_by_department(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $assigned = $this->makeUser(['department_id' => $dept->id], ['member']);
        $unassigned = $this->makeUser(['department_id' => null], ['member']);
        $admin = $this->makeUser([], ['super_admin']);

        $response = $this->actingAs($admin)
            ->getJson('/api/workspace-config/members/by-department')
            ->assertOk();

        $unassignedIds = collect($response->json('unassigned'))->pluck('id')->all();
        $this->assertContains($unassigned->id, $unassignedIds);
        $this->assertNotContains($assigned->id, $unassignedIds);

        $group = collect($response->json('departments'))->firstWhere('id', $dept->id);
        $this->assertNotNull($group);
        $this->assertSame(1, $group['member_count']);
        $this->assertContains($assigned->id, collect($group['members'])->pluck('id')->all());
    }

    public function test_unassigned_members_syncs_departments_from_hrm_org_units(): void
    {
        $this->seed(RoleSeeder::class);

        config([
            'services.hrm.api_base_url' => 'https://hrm.test',
            'services.hrm.api_token' => 'test-token',
        ]);

        Http::fake([
            'https://hrm.test/api/v1/org-units*' => Http::response([
                'data' => [
                    [
                        'uuid' => 'ou-sync-1',
                        'code' => 'PB01',
                        'name' => 'Phòng Kế toán',
                        'type' => 'department',
                        'status' => 'active',
                        'company' => ['uuid' => 'co-1', 'code' => 'VAS', 'name' => 'VA Schools'],
                    ],
                    [
                        'uuid' => 'ou-sync-hq',
                        'code' => 'HQ',
                        'name' => 'Trụ sở',
                        'type' => 'headquarter',
                        'status' => 'active',
                    ],
                ],
                'meta' => ['cursor' => ['next' => null, 'prev' => null, 'count' => 2, 'per_page' => 200]],
            ], 200),
        ]);

        $admin = $this->makeUser([], ['super_admin']);

        $response = $this->actingAs($admin)
            ->getJson('/api/workspace-config/members/unassigned')
            ->assertOk();

        $names = collect($response->json('departments'))->pluck('name')->all();
        $this->assertContains('Phòng Kế toán', $names);
        $this->assertNotContains('Trụ sở', $names);

        $this->assertDatabaseHas('departments', [
            'hrm_org_unit_uuid' => 'ou-sync-1',
            'name' => 'Phòng Kế toán',
        ]);
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
