<?php

namespace Tests\Feature\Dashboard;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\User;
use Tests\TestCase;

class MyDashboardDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_requires_authentication(): void
    {
        $this->getJson('/api/dashboard/me/directory')
            ->assertUnauthorized();
    }

    public function test_directory_returns_members_for_viewer(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $colleague = User::factory()->create([
            'name' => 'Nguyễn Văn A',
            'email' => 'a@vaschools.edu.vn',
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/dashboard/me/directory')
            ->assertOk();

        $names = collect($response->json('members'))->pluck('name');
        $this->assertTrue($names->contains('Nguyễn Văn A'));
        $this->assertTrue($names->contains($viewer->name));
    }
}
