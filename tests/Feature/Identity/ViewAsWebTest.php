<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Tests\TestCase;

class ViewAsWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_view_as_persists_in_web_session_me(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->sync(Role::query()->pluck('id'));

        $response = $this->actingAs($user)
            ->postJson('/api/view-as', ['role_code' => 'member']);

        $response->assertOk()
            ->assertJsonPath('user.active_role', 'member')
            ->assertJsonPath('user.is_impersonating', true);

        $this->actingAs($user)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('active_role', 'member')
            ->assertJsonPath('is_impersonating', true)
            ->assertJsonPath('can_view_as', true);
    }

    public function test_super_admin_payload_hides_department_until_viewing_as_another_role(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'CNTT', 'name' => 'Công nghệ thông tin', 'is_active' => true]);
        $user = User::factory()->create(['status' => 'active', 'department_id' => $dept->id]);
        $user->roles()->sync(Role::query()->pluck('id'));

        $this->actingAs($user)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('active_role', 'super_admin')
            ->assertJsonPath('is_impersonating', false)
            ->assertJsonPath('department', null);

        $this->actingAs($user)
            ->postJson('/api/view-as', ['role_code' => 'department_director'])
            ->assertOk()
            ->assertJsonPath('user.active_role', 'department_director')
            ->assertJsonPath('user.department.id', $dept->id)
            ->assertJsonPath('user.department.code', 'CNTT');

        $this->actingAs($user)
            ->postJson('/api/view-as', ['role_code' => 'super_admin'])
            ->assertOk()
            ->assertJsonPath('user.active_role', 'super_admin')
            ->assertJsonPath('user.is_impersonating', false)
            ->assertJsonPath('user.department', null);

        $this->assertSame($dept->id, $user->fresh()->department_id);
    }
}
