<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Services\SuperAdminBootstrap;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Tests\TestCase;

class SuperAdminBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_me_grants_can_view_as_for_configured_superadmin_email(): void
    {
        $this->seed(RoleSeeder::class);

        $bootstrap = app(SuperAdminBootstrap::class);
        config(['services.superadmin_email' => 'boss@vaschools.edu.vn']);

        $user = User::factory()->create([
            'email' => 'boss@vaschools.edu.vn',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('can_view_as', true)
            ->assertJsonPath('roles', fn ($roles) => in_array('super_admin', $roles, true));
    }
}
