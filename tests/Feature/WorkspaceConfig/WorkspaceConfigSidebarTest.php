<?php

namespace Tests\Feature\WorkspaceConfig;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\DepartmentSidebarConfig;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Tests\TestCase;

class WorkspaceConfigSidebarTest extends TestCase
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

    public function test_director_can_list_and_toggle_sidebar_menu(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->getJson('/api/workspace-config/sidebar')
            ->assertOk()
            ->assertJsonPath('menus.0.menu_key', 'manager.workspace-config.members')
            ->assertJsonPath('menus.0.is_visible', true);

        $this->actingAs($director)
            ->putJson('/api/workspace-config/sidebar', [
                'menu_key' => 'manager.workspace-config.members',
                'is_visible' => false,
            ])
            ->assertOk()
            ->assertJsonPath('menu.menu_key', 'manager.workspace-config.members')
            ->assertJsonPath('menu.is_visible', false);

        $this->assertDatabaseHas('department_sidebar_configs', [
            'department_id' => $dept->id,
            'menu_key' => 'manager.workspace-config.members',
            'is_visible' => false,
        ]);

        $this->actingAs($director)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('hidden_menu_keys.0', 'manager.workspace-config.members');
    }

    public function test_cannot_toggle_unknown_menu_key(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->putJson('/api/workspace-config/sidebar', [
                'menu_key' => 'superadmin.permissions',
                'is_visible' => false,
            ])
            ->assertStatus(422);

        $this->assertSame(0, DepartmentSidebarConfig::query()->count());
    }

    public function test_member_cannot_manage_sidebar(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $member = $this->makeUser(['department_id' => $dept->id], ['member']);

        $this->actingAs($member)
            ->getJson('/api/workspace-config/sidebar')
            ->assertStatus(403);
    }
}
