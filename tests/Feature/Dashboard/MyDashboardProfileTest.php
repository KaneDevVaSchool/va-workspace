<?php

namespace Tests\Feature\Dashboard;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Dashboard\App\Models\DashboardProfileChangeRequest;
use Modules\Identity\App\Models\User;
use Tests\TestCase;

class MyDashboardProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/dashboard/me/profile')
            ->assertUnauthorized();
    }

    public function test_birthday_greeting_requires_authentication(): void
    {
        $this->getJson('/api/dashboard/me/birthday-greeting')
            ->assertUnauthorized();
    }

    public function test_birthday_greeting_returns_false_without_hrm(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $user->assignRole('viewer');

        $this->actingAs($user)
            ->getJson('/api/dashboard/me/birthday-greeting')
            ->assertOk()
            ->assertJsonPath('people', []);
    }

    public function test_profile_returns_workspace_fallback_without_hrm(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $user->assignRole('viewer');

        $this->actingAs($user)
            ->getJson('/api/dashboard/me/profile')
            ->assertOk()
            ->assertJsonPath('workspace.name', 'Test User')
            ->assertJsonPath('hrm', null);

        $response = $this->actingAs($user)->getJson('/api/dashboard/me/profile');
        $keys = collect($response->json('editable_fields'))->pluck('key')->all();
        $this->assertContains('full_name', $keys);
        $this->assertContains('company_email', $keys);
        $this->assertContains('attendance_code', $keys);
        $this->assertContains('job_title', $keys);
        $this->assertContains('department_name', $keys);
        $this->assertContains('hired_at', $keys);
    }

    public function test_avatar_upload_updates_user(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->assignRole('viewer');

        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $response = $this->actingAs($user)
            ->post('/api/dashboard/me/profile/avatar', ['avatar' => $file], [
                'Accept' => 'application/json',
            ])
            ->assertOk();

        $this->assertNotEmpty($response->json('avatar_url'));
        $this->assertSame($response->json('avatar_url'), $user->fresh()->avatar_url);
    }

    public function test_change_request_persists_pending_record(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');

        $this->actingAs($user)
            ->postJson('/api/dashboard/me/profile/change-requests', [
                'changes' => ['phone' => '0901234567'],
                'note' => 'Cập nhật SĐT',
            ])
            ->assertCreated()
            ->assertJsonPath('request.status', DashboardProfileChangeRequest::STATUS_PENDING);

        $this->assertDatabaseHas('dashboard_profile_change_requests', [
            'user_id' => $user->id,
            'status' => DashboardProfileChangeRequest::STATUS_PENDING,
        ]);
    }

    public function test_change_request_accepts_direct_manager_correction(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');

        $this->actingAs($user)
            ->postJson('/api/dashboard/me/profile/change-requests', [
                'changes' => [
                    'manager_name' => 'Trần Quản lý (VA010099)',
                ],
                'note' => 'QLTT trên HRM không đúng với thực tế',
            ])
            ->assertCreated()
            ->assertJsonPath('request.changes.manager_name.label', 'Quản lý trực tiếp')
            ->assertJsonPath('request.changes.manager_name.requested', 'Trần Quản lý (VA010099)');
    }
}
