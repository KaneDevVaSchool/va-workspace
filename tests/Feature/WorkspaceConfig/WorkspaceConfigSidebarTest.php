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
            ->assertJsonPath('menus.0.menu_key', 'home')
            ->assertJsonPath('menus.0.default_label', 'Tổng quan')
            ->assertJsonPath('menus.0.custom_label', null)
            ->assertJsonPath('menus.0.label', 'Tổng quan')
            ->assertJsonPath('menus.0.is_visible', true)
            ->assertJsonPath('menus.0.section', 'general')
            ->assertJsonPath('sections.0.id', 'general')
            ->assertJsonPath('sections.0.label', 'Điều hướng')
            ->assertJsonPath('sections.1.id', 'manager')
            ->assertJsonPath('sections.1.label', 'Quản lý');

        $this->actingAs($director)
            ->putJson('/api/workspace-config/sidebar', [
                'menu_key' => 'home',
                'is_visible' => false,
            ])
            ->assertOk()
            ->assertJsonPath('menu.menu_key', 'home')
            ->assertJsonPath('menu.is_visible', false);

        $this->assertDatabaseHas('department_sidebar_configs', [
            'department_id' => $dept->id,
            'menu_key' => 'home',
            'is_visible' => false,
        ]);

        $this->actingAs($director)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('hidden_menu_keys.0', 'home');
    }

    public function test_director_can_rename_sidebar_menu(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->putJson('/api/workspace-config/sidebar', [
                'menu_key' => 'home',
                'custom_label' => 'Trang chủ phòng',
            ])
            ->assertOk()
            ->assertJsonPath('menu.menu_key', 'home')
            ->assertJsonPath('menu.custom_label', 'Trang chủ phòng')
            ->assertJsonPath('menu.label', 'Trang chủ phòng')
            ->assertJsonPath('menu.default_label', 'Tổng quan')
            ->assertJsonPath('menu.is_visible', true);

        $this->assertDatabaseHas('department_sidebar_configs', [
            'department_id' => $dept->id,
            'menu_key' => 'home',
            'custom_label' => 'Trang chủ phòng',
            'is_visible' => true,
        ]);

        $this->actingAs($director)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('menu_labels.home', 'Trang chủ phòng');

        $this->actingAs($director)
            ->putJson('/api/workspace-config/sidebar', [
                'menu_key' => 'home',
                'is_visible' => false,
            ])
            ->assertOk()
            ->assertJsonPath('menu.is_visible', false)
            ->assertJsonPath('menu.custom_label', 'Trang chủ phòng')
            ->assertJsonPath('menu.label', 'Trang chủ phòng');

        $this->actingAs($director)
            ->putJson('/api/workspace-config/sidebar', [
                'menu_key' => 'home',
                'custom_label' => '',
            ])
            ->assertOk()
            ->assertJsonPath('menu.custom_label', null)
            ->assertJsonPath('menu.label', 'Tổng quan');
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

        $this->actingAs($director)
            ->putJson('/api/workspace-config/sidebar', [
                'menu_key' => 'manager.workspace-config.members',
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

    public function test_director_can_reorder_sidebar_and_move_item_to_top(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->putJson('/api/workspace-config/sidebar/layout', [
                'items' => [
                    ['menu_key' => 'manager.evaluation-score-kit.index', 'section' => 'general'],
                    ['menu_key' => 'home', 'section' => 'general'],
                    ['menu_key' => 'social.feed', 'section' => 'general'],
                    ['menu_key' => 'manager.evaluation.view', 'section' => 'general'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('menus.0.menu_key', 'manager.evaluation-score-kit.index')
            ->assertJsonPath('menus.0.section', 'general')
            ->assertJsonPath('menus.0.sort_order', 0)
            ->assertJsonPath('menus.1.menu_key', 'home');

        $this->assertDatabaseHas('department_sidebar_configs', [
            'department_id' => $dept->id,
            'menu_key' => 'manager.evaluation-score-kit.index',
            'section_key' => 'general',
            'sort_order' => 0,
        ]);

        $me = $this->actingAs($director)
            ->getJson('/api/me')
            ->assertOk()
            ->json();

        $this->assertSame(0, $me['menu_order']['manager.evaluation-score-kit.index']);
        $this->assertSame('general', $me['menu_item_sections']['manager.evaluation-score-kit.index']);
    }

    public function test_director_can_rename_sidebar_section(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->putJson('/api/workspace-config/sidebar/section', [
                'section_key' => 'general',
                'custom_label' => 'Menu chính',
            ])
            ->assertOk()
            ->assertJsonPath('section.id', 'general')
            ->assertJsonPath('section.custom_label', 'Menu chính')
            ->assertJsonPath('section.label', 'Menu chính')
            ->assertJsonPath('sections.0.label', 'Menu chính');

        $this->assertDatabaseHas('department_sidebar_configs', [
            'department_id' => $dept->id,
            'menu_key' => 'section:general',
            'custom_label' => 'Menu chính',
        ]);

        $this->actingAs($director)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('menu_section_labels.general', 'Menu chính')
            ->assertJsonMissingPath('menu_labels.section:general');

        $this->actingAs($director)
            ->putJson('/api/workspace-config/sidebar/section', [
                'section_key' => 'general',
                'custom_label' => '',
            ])
            ->assertOk()
            ->assertJsonPath('section.custom_label', null)
            ->assertJsonPath('section.label', 'Điều hướng');
    }

    public function test_cannot_reorder_with_unknown_or_partial_menu_keys(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->putJson('/api/workspace-config/sidebar/layout', [
                'items' => [
                    ['menu_key' => 'home', 'section' => 'general'],
                ],
            ])
            ->assertStatus(422);

        $this->actingAs($director)
            ->putJson('/api/workspace-config/sidebar/section', [
                'section_key' => 'admin',
                'custom_label' => 'Không được',
            ])
            ->assertStatus(422);
    }

    public function test_globally_hidden_menu_disappears_from_department_config_and_cannot_be_toggled(): void
    {
        $this->seed(RoleSeeder::class);

        $superAdmin = User::factory()->create(['status' => 'active']);
        $superAdmin->roles()->sync(Role::query()->where('code', 'super_admin')->pluck('id'));

        // Superadmin ẩn "Bảng tin nội bộ" Ở MỨC TOÀN HỆ THỐNG.
        $this->actingAs($superAdmin)
            ->putJson('/api/workspace-config/global-menu', [
                'menu_key' => 'social.feed',
                'is_hidden' => true,
            ])
            ->assertOk();

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        // Danh sách cấu hình của phòng ban không còn hiện mục đã bị ẩn global.
        $menuKeys = $this->actingAs($director)
            ->getJson('/api/workspace-config/sidebar')
            ->assertOk()
            ->json('menus.*.menu_key');

        $this->assertNotContains('social.feed', $menuKeys);
        $this->assertContains('home', $menuKeys);

        // Cố toggle trực tiếp qua API (client cũ chưa tải lại trang) vẫn bị chặn.
        $this->actingAs($director)
            ->putJson('/api/workspace-config/sidebar', [
                'menu_key' => 'social.feed',
                'is_visible' => true,
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('department_sidebar_configs', [
            'department_id' => $dept->id,
            'menu_key' => 'social.feed',
        ]);
    }
}
