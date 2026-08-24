<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_super_admin_cannot_view_as(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['status' => 'active']);
        $memberRole = Role::query()->where('code', 'member')->firstOrFail();
        $user->roles()->sync([$memberRole->id]);

        $this->actingAs($user)
            ->postJson('/api/view-as', ['role_code' => 'admin'])
            ->assertForbidden();
    }

    public function test_invalid_role_code_returns_validation_error(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->sync(Role::query()->pluck('id'));

        $this->actingAs($user)
            ->postJson('/api/view-as', ['role_code' => 'not_a_real_role'])
            ->assertUnprocessable();
    }

    public function test_logout_requires_authentication_for_me(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->sync(Role::query()->pluck('id'));

        $this->actingAs($user)
            ->postJson('/api/view-as', ['role_code' => 'member'])
            ->assertOk()
            ->assertJsonPath('user.is_impersonating', true);

        $this->actingAs($user)
            ->postJson('/logout')
            ->assertOk();

        $this->getJson('/api/me')->assertUnauthorized();
    }
}
