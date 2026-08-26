<?php

namespace Tests\Feature\WorkspaceConfig;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Tests\TestCase;

class WorkspaceConfigGlobalMenuVisibilityTest extends TestCase
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

    private function makeSuperAdmin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->sync(Role::query()->where('code', 'super_admin')->pluck('id'));

        return $user;
    }

    public function test_superadmin_can_list_and_toggle_global_menu(): void
    {
        $this->seed(RoleSeeder::class);
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin)
            ->getJson('/api/workspace-config/global-menu')
            ->assertOk()
            ->assertJsonPath('menus.0.menu_key', 'home')
            ->assertJsonPath('menus.0.is_hidden', false)
            ->assertJsonCount(10, 'menus');

        $this->actingAs($superAdmin)
            ->putJson('/api/workspace-config/global-menu', [
                'menu_key' => 'manager.social.moderation',
                'is_hidden' => true,
            ])
            ->assertOk()
            ->assertJsonFragment(['menu_key' => 'manager.social.moderation', 'is_hidden' => true]);

        $this->assertDatabaseHas('global_menu_visibilities', [
            'menu_key' => 'manager.social.moderation',
            'is_hidden' => true,
        ]);
    }

    public function test_non_superadmin_forbidden(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->getJson('/api/workspace-config/global-menu')
            ->assertStatus(403);

        $this->actingAs($director)
            ->putJson('/api/workspace-config/global-menu', [
                'menu_key' => 'home',
                'is_hidden' => true,
            ])
            ->assertStatus(403);
    }

    public function test_me_endpoint_returns_globally_hidden_menu_keys_for_regular_user_and_director(): void
    {
        $this->seed(RoleSeeder::class);
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin)->putJson('/api/workspace-config/global-menu', [
            'menu_key' => 'manager.social.moderation',
            'is_hidden' => true,
        ])->assertOk();

        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Dept 1', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $member = $this->makeUser([], ['member']);

        $this->actingAs($director)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('globally_hidden_menu_keys.0', 'manager.social.moderation');

        $this->actingAs($member)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('globally_hidden_menu_keys.0', 'manager.social.moderation');
    }

    public function test_super_admin_me_reflects_global_hidden_keys(): void
    {
        // Dữ liệu ở /api/me KHÔNG bị lọc cho super_admin. AppSidebar.vue::itemPasses()
        // (phía client, không test được bằng Feature test PHP) áp dụng
        // globally_hidden_menu_keys cho MỌI tài khoản kể cả super_admin —
        // không có ngoại lệ ở tầng sidebar UI (khác với middleware
        // menu.not_hidden ở tầng route, nơi super_admin thật vẫn bypass).
        $this->seed(RoleSeeder::class);
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin)->putJson('/api/workspace-config/global-menu', [
            'menu_key' => 'manager.social.moderation',
            'is_hidden' => true,
        ])->assertOk();

        $this->actingAs($superAdmin)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('globally_hidden_menu_keys.0', 'manager.social.moderation');
    }

    public function test_reject_invalid_menu_key(): void
    {
        $this->seed(RoleSeeder::class);
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin)
            ->putJson('/api/workspace-config/global-menu', [
                'menu_key' => 'khong-ton-tai',
                'is_hidden' => true,
            ])
            ->assertStatus(422);
    }

    public function test_cannot_hide_the_global_menu_config_page_itself(): void
    {
        $this->seed(RoleSeeder::class);
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin)
            ->putJson('/api/workspace-config/global-menu', [
                'menu_key' => 'superadmin.workspace-config.global-menu',
                'is_hidden' => true,
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('global_menu_visibilities', [
            'menu_key' => 'superadmin.workspace-config.global-menu',
            'is_hidden' => true,
        ]);
    }

    public function test_route_blocked_when_menu_globally_hidden(): void
    {
        $this->seed(RoleSeeder::class);
        $superAdmin = $this->makeSuperAdmin();

        $admin = $this->makeUser([], ['admin']);

        // Trước khi ẩn: admin có social.review nên vào được.
        $this->actingAs($admin)
            ->getJson('/api/social/moderation')
            ->assertOk();

        $this->actingAs($superAdmin)->putJson('/api/workspace-config/global-menu', [
            'menu_key' => 'manager.social.moderation',
            'is_hidden' => true,
        ])->assertOk();

        // Sau khi ẩn: admin bị chặn bởi middleware menu.not_hidden dù vẫn có social.review.
        $this->actingAs($admin)
            ->getJson('/api/social/moderation')
            ->assertStatus(403);

        // super_admin thật luôn bypass.
        $this->actingAs($superAdmin)
            ->getJson('/api/social/moderation')
            ->assertOk();
    }

    public function test_route_still_blocked_for_admin_even_though_admin_has_permission(): void
    {
        $this->seed(RoleSeeder::class);
        $superAdmin = $this->makeSuperAdmin();
        $admin = $this->makeUser([], ['admin']);

        $this->actingAs($superAdmin)->putJson('/api/workspace-config/global-menu', [
            'menu_key' => 'superadmin.activity',
            'is_hidden' => true,
        ])->assertOk();

        // role:super_admin,admin vốn cho admin vào — nhưng global hide vẫn chặn.
        $this->actingAs($admin)
            ->getJson('/api/activity-logs')
            ->assertStatus(403);

        $this->actingAs($superAdmin)
            ->getJson('/api/activity-logs')
            ->assertOk();
    }

    public function test_super_admin_impersonating_is_subject_to_global_hide(): void
    {
        $this->seed(RoleSeeder::class);
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin)->putJson('/api/workspace-config/global-menu', [
            'menu_key' => 'manager.social.moderation',
            'is_hidden' => true,
        ])->assertOk();

        $this->actingAs($superAdmin)
            ->postJson('/api/view-as', ['role_code' => 'admin'])
            ->assertOk();

        $this->actingAs($superAdmin)
            ->getJson('/api/social/moderation')
            ->assertStatus(403);
    }
}
