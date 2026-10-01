<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Tests\TestCase;

class DefaultMemberRoleBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_me_assigns_member_role_when_user_has_none(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create([
            'status' => 'active',
            'department_id' => null,
        ]);

        $this->actingAs($user)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('roles', ['member'])
            ->assertJson(fn ($json) => $json->where('granted_permissions', fn ($perms) => in_array('dashboard.view', $perms, true))
                ->etc());

        $this->assertTrue($user->fresh()->hasRole('member'));
    }

    public function test_api_me_does_not_replace_existing_roles(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['status' => 'active']);
        $director = \Modules\Identity\App\Models\Role::query()->where('code', 'department_director')->firstOrFail();
        $user->roles()->sync([$director->id]);

        $this->actingAs($user)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('roles', ['department_director']);

        $this->assertFalse($user->fresh()->hasRole('member'));
    }
}
