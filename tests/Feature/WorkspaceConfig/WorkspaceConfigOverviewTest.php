<?php

namespace Tests\Feature\WorkspaceConfig;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutHrmSync();
    }

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
            'database.connections.hrm.database' => null,
        ]);
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

    public function test_overview_director_prefers_hrm_org_unit_manager(): void
    {
        $this->seed(RoleSeeder::class);

        config([
            'database.connections.hrm' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => 'va_hrm_',
                'foreign_key_constraints' => false,
            ],
        ]);
        DB::purge('hrm');
        $schema = Schema::connection('hrm');
        $schema->create('companies', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->nullable();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
        });
        $schema->create('employees', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid');
            $table->string('full_name')->nullable();
            $table->string('company_email')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        $schema->create('org_units', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('type')->nullable();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->string('short_name')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('manager_employee_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        DB::connection('hrm')->table('employees')->insert([
            'id' => 7,
            'uuid' => 'emp-mgr-1',
            'full_name' => 'Trưởng HRM',
            'company_email' => 'truong.hrm@vaschools.edu.vn',
        ]);
        DB::connection('hrm')->table('org_units')->insert([
            'id' => 1,
            'uuid' => 'ou-mgr-1',
            'type' => 'department',
            'code' => 'HRM',
            'name' => 'Phòng HRM',
            'status' => 'active',
            'manager_employee_id' => 7,
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

    public function test_members_by_department_reads_hrm_database_without_calling_api(): void
    {
        $this->seed(RoleSeeder::class);
        Http::fake();

        $employeeUuid = '9968e9b8-a011-4d46-b6ad-3ff28ac584a6';
        $this->useHrmDirectory([
            $this->hrmEmployee($employeeUuid, 'a.nguyen@vaschools.edu.vn', 'Nguyễn Văn A'),
        ], [
            $this->hrmOrgUnit('ou-emp-1', 'PB01', 'Phòng Kế toán'),
        ]);

        $admin = $this->makeUser([], ['super_admin']);

        $this->actingAs($admin)
            ->getJson('/api/workspace-config/members/by-department')
            ->assertOk()
            ->assertJsonPath('source', 'hrm_database')
            ->assertJsonPath('members.0.email', 'a.nguyen@vaschools.edu.vn')
            ->assertJsonPath('members.0.org_unit.name', 'Phòng Kế toán')
            ->assertJsonPath('members.0.department', null)
            ->assertJsonPath('members.0.has_workspace_account', false);

        Http::assertNothingSent();
        $this->assertDatabaseMissing('users', ['email' => 'a.nguyen@vaschools.edu.vn']);

        $department = Department::query()->where('hrm_org_unit_uuid', 'ou-emp-1')->first();
        $this->assertNotNull($department);

        $this->actingAs($admin)
            ->putJson("/api/workspace-config/members/hrm/{$employeeUuid}/department", [
                'department_id' => $department->id,
            ])
            ->assertOk()
            ->assertJsonPath('member.department.id', $department->id)
            ->assertJsonPath('member.has_workspace_account', true);

        $this->assertDatabaseHas('users', [
            'hrm_employee_uuid' => $employeeUuid,
            'email' => 'a.nguyen@vaschools.edu.vn',
            'department_id' => $department->id,
        ]);
    }

    public function test_hrm_roster_does_not_override_manually_assigned_department(): void
    {
        $this->seed(RoleSeeder::class);
        Http::fake();

        $manualDept = Department::query()->create(['code' => 'PB02', 'name' => 'Phòng Nhân sự', 'is_active' => true]);
        $existing = $this->makeUser([
            'department_id' => $manualDept->id,
            'hrm_employee_uuid' => '11111111-1111-4111-8111-111111111111',
            'email' => 'b.tran@vaschools.edu.vn',
        ], ['member']);

        $this->useHrmDirectory([
            $this->hrmEmployee('11111111-1111-4111-8111-111111111111', 'b.tran@vaschools.edu.vn', 'Trần Thị B'),
        ], [
            $this->hrmOrgUnit('ou-emp-2', 'PB01', 'Phòng Kế toán'),
        ]);

        $admin = $this->makeUser([], ['super_admin']);

        $this->actingAs($admin)
            ->getJson('/api/workspace-config/members/by-department')
            ->assertOk()
            ->assertJsonPath('members.0.department.id', $manualDept->id);

        Http::assertNothingSent();
        $this->assertDatabaseHas('users', [
            'id' => $existing->id,
            'department_id' => $manualDept->id,
        ]);
    }

    public function test_hrm_roster_relinks_company_when_code_already_exists(): void
    {
        $this->seed(RoleSeeder::class);
        Http::fake();

        Company::query()->create([
            'hrm_uuid' => '4ddd509c-eefd-4fb8-bd3d-a781b73da5dc',
            'code' => 'VAS',
            'name' => 'VA Schools cũ',
        ]);

        $this->useHrmDirectory([
            $this->hrmEmployee('9968e9b8-a011-4d46-b6ad-3ff28ac584a6', 'a.nguyen@vaschools.edu.vn', 'Nguyễn Văn A'),
        ], [
            $this->hrmOrgUnit('ou-emp-1', 'PB01', 'Phòng Kế toán'),
        ]);

        $admin = $this->makeUser([], ['super_admin']);

        $this->actingAs($admin)
            ->getJson('/api/workspace-config/members/by-department')
            ->assertOk()
            ->assertJsonPath('source', 'hrm_database')
            ->assertJsonCount(1, 'members');

        $this->assertSame(1, Company::query()->where('code', 'VAS')->count());
        $this->assertDatabaseHas('companies', [
            'code' => 'VAS',
            'hrm_uuid' => 'co-1',
            'name' => 'VA Schools',
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
        $member = $this->makeUser([
            'department_id' => null,
            'employee_code' => 'NV100',
            'job_title_name' => 'Giáo viên',
            'manager_display_name' => 'Trần Quản lý',
        ], ['member']);
        $admin = $this->makeUser([], ['super_admin']);

        $this->actingAs($admin)
            ->putJson("/api/workspace-config/members/{$member->id}/department", [
                'department_id' => $dept->id,
            ])
            ->assertOk()
            ->assertJsonPath('member.department.id', $dept->id)
            ->assertJsonPath('member.employee_code', 'NV100')
            ->assertJsonPath('member.job_title_name', 'Giáo viên')
            ->assertJsonPath('member.manager_display_name', 'Trần Quản lý')
            ->assertJsonPath('member.concurrent_positions', []);

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

    /**
     * @param  list<array<string, mixed>>  $employees
     * @param  list<array<string, mixed>>  $orgUnits
     */
    private function useHrmDirectory(array $employees, array $orgUnits): void
    {
        config(['database.connections.hrm.database' => 'hrm-test']);

        $this->mock(HrmEmployeeDirectory::class, function ($mock) use ($employees, $orgUnits): void {
            $mock->shouldReceive('load')->andReturn([
                'employees' => $employees,
                'org_units' => $orgUnits,
            ]);
            $mock->shouldReceive('findEmployee')->andReturnUsing(
                fn (string $uuid) => collect($employees)->firstWhere('uuid', $uuid)
            );
        });
    }

    /** @return array<string, mixed> */
    private function hrmEmployee(string $uuid, string $email, string $name): array
    {
        return [
            'uuid' => $uuid,
            'code' => 'NV001',
            'full_name' => $name,
            'company_email' => $email,
            'personal_email' => null,
            'phone' => null,
            'status' => 'active',
            'job_title' => 'Nhân viên',
            'level_name' => 'Nhân viên',
            'company_uuid' => 'co-1',
            'company_code' => 'VAS',
            'company_name' => 'VA Schools',
            'org_unit_uuid' => 'ou-emp-1',
            'org_unit_name' => 'Phòng Kế toán',
            'org_unit_path' => '/ou-emp-1',
            'manager_name' => 'Trần Quản lý',
            'concurrent_positions' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function hrmOrgUnit(string $uuid, string $code, string $name): array
    {
        return [
            'uuid' => $uuid,
            'code' => $code,
            'name' => $name,
            'short_name' => null,
            'type' => 'department',
            'status' => 'active',
            'company' => ['uuid' => 'co-1', 'code' => 'VAS', 'name' => 'VA Schools'],
        ];
    }
}
