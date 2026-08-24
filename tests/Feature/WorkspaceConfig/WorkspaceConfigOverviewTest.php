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
            'menu_key' => 'manager.workspace-config.members',
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
}
