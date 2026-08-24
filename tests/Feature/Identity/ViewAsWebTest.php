<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
