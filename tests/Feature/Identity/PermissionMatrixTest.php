<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Exceptions\PermissionKeyReserved;
use Modules\Identity\App\Exceptions\ScopeNotFound;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\PermissionGrant;
use Modules\Identity\App\Models\Role;
use Modules\Identity\App\Models\Team;
use Modules\Identity\App\Services\PermissionService;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Tests\TestCase;

/**
 * Xem plans/2026-08-24-quan-ly-phan-quyen-superadmin.md mục 6a (Test Plan).
 */
class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PermissionService
    {
        return app(PermissionService::class);
    }

    private function makeUser(array $attributes = [], array $roles = []): User
    {
        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));

        if (! empty($roles)) {
            $roleIds = Role::query()->whereIn('code', $roles)->pluck('id');
            $user->roles()->sync($roleIds);
        }

        return $user;
    }

    // ------------------------------------------------------------------
    // Config default / hierarchy match
    // ------------------------------------------------------------------

    public function test_config_default_used_when_no_override(): void
    {
        $this->seed(RoleSeeder::class);

        // department_director has 'task.delegate' explicitly in config matrix
        $this->assertTrue($this->service()->roleAllows('department_director', 'task.delegate'));

        // 'member' does NOT have 'task.delegate' in config matrix
        $this->assertFalse($this->service()->roleAllows('member', 'task.delegate'));

        // wildcard match: 'admin' has 'task.*' → matches 'task.delegate'
        $this->assertTrue($this->service()->roleAllows('admin', 'task.delegate'));
    }

    // ------------------------------------------------------------------
    // Global override
    // ------------------------------------------------------------------

    public function test_global_override_grants_key_role_does_not_have_by_default(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertFalse($this->service()->roleAllows('member', 'task.delegate'));

        $this->service()->setGrant('member', 'task.delegate', true, 'global', null, 1);

        $this->assertTrue($this->service()->roleAllows('member', 'task.delegate'));
    }

    public function test_global_override_revokes_key_role_has_by_default(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertTrue($this->service()->roleAllows('department_director', 'task.delegate'));

        $this->service()->setGrant('department_director', 'task.delegate', false, 'global', null, 1);

        $this->assertFalse($this->service()->roleAllows('department_director', 'task.delegate'));
    }

    // ------------------------------------------------------------------
    // Scoped override precedence (2 bậc: scoped → global → config)
    // ------------------------------------------------------------------

    public function test_scoped_override_wins_over_global_override_when_scoped_grants(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);

        $this->service()->setGrant('member', 'task.delegate', false, 'global', null, 1);
        $this->service()->setGrant('member', 'task.delegate', true, 'department', $dept->id, 1);

        $this->assertTrue($this->service()->roleAllows('member', 'task.delegate', 'department', $dept->id));
    }

    public function test_scoped_override_wins_over_global_override_when_scoped_revokes(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $team = Team::query()->create(['department_id' => $dept->id, 'name' => 'Team 1']);

        $this->service()->setGrant('member', 'task.delegate', true, 'global', null, 1);
        $this->service()->setGrant('member', 'task.delegate', false, 'team', $team->id, 1);

        $this->assertFalse($this->service()->roleAllows('member', 'task.delegate', 'team', $team->id));
    }

    public function test_falls_back_to_global_override_when_no_scoped_override_exists(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);

        $this->service()->setGrant('member', 'task.delegate', true, 'global', null, 1);

        // No department-scoped override exists → falls back to global override
        $this->assertTrue($this->service()->roleAllows('member', 'task.delegate', 'department', $dept->id));
    }

    // ------------------------------------------------------------------
    // Scope matching at allows() level (user must belong to scope)
    // ------------------------------------------------------------------

    public function test_allows_rejects_when_user_does_not_match_scope_regardless_of_overrides(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $otherDept = Department::query()->create(['code' => 'D2', 'name' => 'Dept 2', 'is_active' => true]);

        $user = $this->makeUser(['department_id' => $dept->id], ['member']);

        $this->service()->setGrant('member', 'task.delegate', true, 'department', $otherDept->id, 1);

        $this->assertFalse($this->service()->allows($user, 'task.delegate', 'department', $otherDept->id));
    }

    // ------------------------------------------------------------------
    // Reserved keys
    // ------------------------------------------------------------------

    public function test_reserved_key_cannot_be_granted_to_regular_role(): void
    {
        $this->seed(RoleSeeder::class);

        $this->expectException(PermissionKeyReserved::class);
        $this->service()->setGrant('member', 'permissions.manage', true, 'global', null, 1);
    }

    public function test_reserved_key_exception_role_can_be_granted(): void
    {
        $this->seed(RoleSeeder::class);

        $this->service()->setGrant('director_officer', 'initiative.assign_department', true, 'global', null, 1);

        $this->assertDatabaseHas('permission_grants', [
            'role_code' => 'director_officer',
            'permission_key' => 'initiative.assign_department',
            'granted' => true,
        ]);
    }

    // ------------------------------------------------------------------
    // super_admin bypass
    // ------------------------------------------------------------------

    public function test_super_admin_bypasses_everything_except_when_impersonating(): void
    {
        $this->seed(RoleSeeder::class);
        $superAdmin = $this->makeUser([], ['super_admin']);

        $this->assertTrue($this->service()->allows($superAdmin, 'permissions.manage'));
        $this->assertTrue($this->service()->allows($superAdmin, 'anything.not_in_catalog'));

        $this->actingAs($superAdmin)->postJson('/api/view-as', ['role_code' => 'member'])->assertOk();

        $this->assertFalse($this->service()->allows($superAdmin, 'permissions.manage'));
    }

    // ------------------------------------------------------------------
    // matrixFor() distinguishes "has override" vs "effective differs from default"
    // ------------------------------------------------------------------

    public function test_matrix_for_distinguishes_override_existence_from_effective_value(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);

        // config default for department_director.task.delegate = true
        $this->service()->setGrant('department_director', 'task.delegate', false, 'global', null, 1);
        $this->service()->setGrant('department_director', 'task.delegate', true, 'department', $dept->id, 1);

        $matrix = $this->service()->matrixFor('department', $dept->id);
        $cell = $matrix['department_director']['task.delegate'];

        $this->assertTrue($cell['default']);
        $this->assertTrue($cell['effective']); // trùng default
        $this->assertNotNull($cell['global_override']); // nhưng override vẫn tồn tại
        $this->assertNotNull($cell['scoped_override']);
        $this->assertSame('scoped', $cell['effective_source']);
    }

    // ------------------------------------------------------------------
    // CRUD grant via API
    // ------------------------------------------------------------------

    public function test_put_grants_creates_and_updates_without_duplicate_rows(): void
    {
        $this->seed(RoleSeeder::class);
        $superAdmin = $this->makeUser([], ['super_admin']);

        $payload = [
            'role_code' => 'member',
            'permission_key' => 'task.delegate',
            'granted' => true,
            'scope_type' => 'global',
        ];

        $this->actingAs($superAdmin)->putJson('/api/permissions/grants', $payload)->assertOk();
        $this->actingAs($superAdmin)->putJson('/api/permissions/grants', array_merge($payload, ['granted' => false]))->assertOk();

        $this->assertDatabaseCount('permission_grants', 1);
        $this->assertDatabaseHas('permission_grants', [
            'role_code' => 'member',
            'permission_key' => 'task.delegate',
            'granted' => false,
        ]);
    }

    public function test_delete_grants_removes_only_the_targeted_scope(): void
    {
        $this->seed(RoleSeeder::class);
        $superAdmin = $this->makeUser([], ['super_admin']);
        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);

        $this->service()->setGrant('member', 'task.delegate', true, 'global', null, 1);
        $this->service()->setGrant('member', 'task.delegate', true, 'department', $dept->id, 1);

        $this->actingAs($superAdmin)->deleteJson('/api/permissions/grants', [
            'role_code' => 'member',
            'permission_key' => 'task.delegate',
            'scope_type' => 'department',
            'scope_id' => $dept->id,
        ])->assertOk();

        $this->assertDatabaseCount('permission_grants', 1);
        $this->assertDatabaseHas('permission_grants', ['scope_type' => 'global']);
        $this->assertDatabaseMissing('permission_grants', ['scope_type' => 'department']);
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    public function test_team_scope_with_nonexistent_id_is_rejected_by_service(): void
    {
        $this->seed(RoleSeeder::class);

        $this->expectException(ScopeNotFound::class);
        $this->service()->setGrant('member', 'task.delegate', true, 'team', 999999, 1);
    }

    public function test_put_grants_rejects_nonexistent_team_scope_id_with_422(): void
    {
        $this->seed(RoleSeeder::class);
        $superAdmin = $this->makeUser([], ['super_admin']);

        $this->actingAs($superAdmin)->putJson('/api/permissions/grants', [
            'role_code' => 'member',
            'permission_key' => 'task.delegate',
            'granted' => true,
            'scope_type' => 'team',
            'scope_id' => 999999,
        ])->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // API access control
    // ------------------------------------------------------------------

    public function test_non_super_admin_cannot_access_permission_api(): void
    {
        $this->seed(RoleSeeder::class);
        $member = $this->makeUser([], ['member']);

        $this->actingAs($member)->getJson('/api/permissions/matrix')->assertStatus(403);
        $this->actingAs($member)->putJson('/api/permissions/grants', [
            'role_code' => 'member',
            'permission_key' => 'task.delegate',
            'granted' => true,
            'scope_type' => 'global',
        ])->assertStatus(403);
    }

    public function test_matrix_api_returns_only_active_permissions(): void
    {
        $this->seed(RoleSeeder::class);
        $superAdmin = $this->makeUser([], ['super_admin']);

        $response = $this->actingAs($superAdmin)->getJson('/api/permissions/matrix');
        $response->assertOk();

        $keys = collect($response->json('permissions'))->pluck('key')->all();

        $this->assertContains('team.manage', $keys);
        $this->assertContains('dashboard.view', $keys);
        $this->assertNotContains('task.delegate', $keys);
        $this->assertNotContains('ai_account.*', $keys);
        $this->assertNotContains('initiative.create', $keys);

        $firstRoleRow = collect($response->json('matrix'))->first();
        $this->assertIsArray($firstRoleRow);
        $this->assertArrayHasKey('team.manage', $firstRoleRow);
        $this->assertArrayNotHasKey('task.delegate', $firstRoleRow);
    }

    public function test_super_admin_viewing_as_another_role_cannot_access_permission_api(): void
    {
        $this->seed(RoleSeeder::class);
        $superAdmin = $this->makeUser([], ['super_admin']);

        $this->actingAs($superAdmin)->postJson('/api/view-as', ['role_code' => 'member'])->assertOk();

        $this->actingAs($superAdmin)->getJson('/api/permissions/matrix')->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // Team
    // ------------------------------------------------------------------

    public function test_team_lead_must_belong_to_same_department(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $otherDept = Department::query()->create(['code' => 'D2', 'name' => 'Dept 2', 'is_active' => true]);
        $outsider = $this->makeUser(['department_id' => $otherDept->id]);

        $this->expectException(\Modules\Identity\App\Exceptions\TeamLeadNotInDepartment::class);
        app(\Modules\Identity\App\Services\TeamService::class)->create([
            'department_id' => $dept->id,
            'name' => 'Team X',
            'team_lead_id' => $outsider->id,
        ]);
    }

    public function test_same_user_can_lead_multiple_teams(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $lead = $this->makeUser(['department_id' => $dept->id]);

        $teamService = app(\Modules\Identity\App\Services\TeamService::class);
        $teamA = $teamService->create(['department_id' => $dept->id, 'name' => 'Team A', 'team_lead_id' => $lead->id]);
        $teamB = $teamService->create(['department_id' => $dept->id, 'name' => 'Team B', 'team_lead_id' => $lead->id]);

        $this->assertSame($lead->id, $teamA->team_lead_id);
        $this->assertSame($lead->id, $teamB->team_lead_id);
    }
}
