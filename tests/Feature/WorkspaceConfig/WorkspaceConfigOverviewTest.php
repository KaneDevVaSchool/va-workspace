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

    /** Tránh overview/unassigned gọi HRM thật khi .env test có token. */
    private function withoutHrmSync(): void
    {
        config([
            'services.hrm.api_base_url' => null,
            'services.hrm.api_token' => null,
        ]);
    }

    public function test_super_admin_overview_includes_inactive_and_config_flags(): void
    {
        $this->withoutHrmSync();
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
        $this->withoutHrmSync();
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

    public function test_overview_director_prefers_hrm_org_unit_manager(): void
    {
        $this->seed(RoleSeeder::class);

        config([
            'services.hrm.api_base_url' => 'https://hrm.test',
            'services.hrm.api_token' => 'test-token',
            'services.hrm.department_manager_sync_ttl' => 60,
        ]);

        $dept = Department::query()->create([
            'code' => 'D-HRM',
            'name' => 'Phòng HRM',
            'is_active' => true,
            'hrm_org_unit_uuid' => 'ou-mgr-1',
        ]);

        Department::query()->create([
            'code' => 'LOCAL-ONLY',
            'name' => 'Phòng seed local',
            'is_active' => true,
        ]);

        Http::fake([
            'https://hrm.test/api/v1/org-units*' => Http::response([
                'data' => [
                    [
                        'uuid' => 'ou-mgr-1',
                        'code' => 'HRM',
                        'name' => 'Phòng HRM',
                        'type' => 'department',
                        'status' => 'active',
                    ],
                ],
                'meta' => ['cursor' => ['next' => null, 'prev' => null, 'count' => 1, 'per_page' => 200]],
            ], 200),
            'https://hrm.test/api/v1/org-units/ou-mgr-1' => Http::response([
                'data' => [
                    'uuid' => 'ou-mgr-1',
                    'code' => 'HRM',
                    'name' => 'Phòng HRM',
                    'type' => 'department',
                    'status' => 'active',
                    'manager' => [
                        'uuid' => 'emp-mgr-1',
                        'code' => 'NV001',
                        'full_name' => 'Trưởng HRM',
                    ],
                ],
            ], 200),
            'https://hrm.test/api/v1/employees/emp-mgr-1' => Http::response([
                'data' => [
                    'uuid' => 'emp-mgr-1',
                    'code' => 'NV001',
                    'full_name' => 'Trưởng HRM',
                    'status' => 'active',
                    'company_email' => 'truong.hrm@vaschools.edu.vn',
                    'manager_uuid' => null,
                    'manager_code' => null,
                    'manager_email' => null,
                    'primary_assignment' => null,
                    'concurrent_assignments' => [],
                ],
            ], 200),
            'https://hrm.test/api/v1/employees/emp-mgr-1/manager' => Http::response(['data' => null], 200),
        ]);

        $admin = $this->makeUser([], ['super_admin']);

        $this->actingAs($admin)
            ->getJson('/api/workspace-config/overview')
            ->assertOk()
            ->assertJsonCount(1, 'departments')
            ->assertJsonPath('departments.0.id', $dept->id)
            ->assertJsonPath('departments.0.director.name', 'Trưởng HRM')
            ->assertJsonPath('departments.0.director.email', 'truong.hrm@vaschools.edu.vn');
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

    public function test_members_by_department_syncs_new_employees_from_hrm_with_department(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create([
            'code' => 'PB01',
            'name' => 'Phòng Kế toán',
            'is_active' => true,
            'hrm_org_unit_uuid' => 'ou-emp-1',
        ]);

        config([
            'services.hrm.api_base_url' => 'https://hrm.test',
            'services.hrm.api_token' => 'test-token',
        ]);

        Http::fake([
            'https://hrm.test/api/v1/org-units*' => Http::response([
                'data' => [],
                'meta' => ['cursor' => ['next' => null, 'prev' => null, 'count' => 0, 'per_page' => 200]],
            ], 200),
            'https://hrm.test/api/v1/employees/emp-uuid-1/manager' => Http::response(['data' => null], 200),
            'https://hrm.test/api/v1/employees/emp-uuid-1' => Http::response([
                'data' => [
                    'uuid' => 'emp-uuid-1',
                    'code' => 'NV001',
                    'full_name' => 'Nguyễn Văn A',
                    'status' => 'active',
                    'company_email' => 'a.nguyen@vaschools.edu.vn',
                    'manager_uuid' => null,
                    'manager_code' => null,
                    'manager_email' => null,
                    'primary_assignment' => [
                        'is_primary' => true,
                        'is_current' => true,
                        'company' => ['uuid' => 'co-1', 'code' => 'VAS', 'name' => 'VA Schools'],
                        'org_unit' => ['uuid' => 'ou-emp-1', 'name' => 'Phòng Kế toán', 'path' => '/ou-emp-1'],
                        'position' => ['title' => 'Nhân viên', 'level' => null],
                        'effective_from' => null,
                        'effective_to' => null,
                    ],
                    'concurrent_assignments' => [],
                ],
            ], 200),
            'https://hrm.test/api/v1/employees*' => Http::response([
                'data' => [
                    [
                        'uuid' => 'emp-uuid-1',
                        'code' => 'NV001',
                        'full_name' => 'Nguyễn Văn A',
                        'status' => 'active',
                        'company_email' => 'a.nguyen@vaschools.edu.vn',
                        'personal_email' => null,
                    ],
                ],
                'meta' => ['cursor' => ['next' => null, 'prev' => null, 'count' => 1, 'per_page' => 200]],
            ], 200),
        ]);

        $admin = $this->makeUser([], ['super_admin']);

        $response = $this->actingAs($admin)
            ->getJson('/api/workspace-config/members/by-department')
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'hrm_employee_uuid' => 'emp-uuid-1',
            'email' => 'a.nguyen@vaschools.edu.vn',
            'department_id' => $dept->id,
        ]);

        $group = collect($response->json('departments'))->firstWhere('id', $dept->id);
        $this->assertNotNull($group);
        $this->assertContains('a.nguyen@vaschools.edu.vn', collect($group['members'])->pluck('email')->all());
    }

    public function test_employee_sync_does_not_override_manually_assigned_department(): void
    {
        $this->seed(RoleSeeder::class);

        $hrmDept = Department::query()->create([
            'code' => 'PB01',
            'name' => 'Phòng Kế toán',
            'is_active' => true,
            'hrm_org_unit_uuid' => 'ou-emp-2',
        ]);
        $manualDept = Department::query()->create(['code' => 'PB02', 'name' => 'Phòng Nhân sự', 'is_active' => true]);

        $existing = $this->makeUser([
            'department_id' => $manualDept->id,
            'hrm_employee_uuid' => 'emp-uuid-2',
            'email' => 'b.tran@vaschools.edu.vn',
        ], ['member']);

        config([
            'services.hrm.api_base_url' => 'https://hrm.test',
            'services.hrm.api_token' => 'test-token',
        ]);

        Http::fake([
            'https://hrm.test/api/v1/org-units*' => Http::response([
                'data' => [],
                'meta' => ['cursor' => ['next' => null, 'prev' => null, 'count' => 0, 'per_page' => 200]],
            ], 200),
            'https://hrm.test/api/v1/employees/emp-uuid-2/manager' => Http::response(['data' => null], 200),
            'https://hrm.test/api/v1/employees/emp-uuid-2' => Http::response([
                'data' => [
                    'uuid' => 'emp-uuid-2',
                    'code' => 'NV002',
                    'full_name' => 'Trần Thị B',
                    'status' => 'active',
                    'company_email' => 'b.tran@vaschools.edu.vn',
                    'manager_uuid' => null,
                    'manager_code' => null,
                    'manager_email' => null,
                    'primary_assignment' => [
                        'is_primary' => true,
                        'is_current' => true,
                        'company' => ['uuid' => 'co-1', 'code' => 'VAS', 'name' => 'VA Schools'],
                        'org_unit' => ['uuid' => 'ou-emp-2', 'name' => 'Phòng Kế toán', 'path' => '/ou-emp-2'],
                        'position' => ['title' => 'Nhân viên', 'level' => null],
                        'effective_from' => null,
                        'effective_to' => null,
                    ],
                    'concurrent_assignments' => [],
                ],
            ], 200),
            'https://hrm.test/api/v1/employees*' => Http::response([
                'data' => [
                    [
                        'uuid' => 'emp-uuid-2',
                        'code' => 'NV002',
                        'full_name' => 'Trần Thị B',
                        'status' => 'active',
                        'company_email' => 'b.tran@vaschools.edu.vn',
                        'personal_email' => null,
                    ],
                ],
                'meta' => ['cursor' => ['next' => null, 'prev' => null, 'count' => 1, 'per_page' => 200]],
            ], 200),
        ]);

        $admin = $this->makeUser([], ['super_admin']);

        $this->actingAs($admin)
            ->getJson('/api/workspace-config/members/by-department')
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $existing->id,
            'department_id' => $manualDept->id,
        ]);
        $this->assertDatabaseMissing('users', [
            'id' => $existing->id,
            'department_id' => $hrmDept->id,
        ]);
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

    public function test_assigning_department_gives_unroled_user_member_role(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $member = $this->makeUser(['department_id' => null]);
        $admin = $this->makeUser([], ['super_admin']);

        $this->actingAs($admin)
            ->putJson("/api/workspace-config/members/{$member->id}/department", [
                'department_id' => $dept->id,
            ])
            ->assertOk()
            ->assertJsonPath('member.roles.0.code', 'member');

        $this->assertTrue($member->fresh()->roles()->where('code', 'member')->exists());
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
