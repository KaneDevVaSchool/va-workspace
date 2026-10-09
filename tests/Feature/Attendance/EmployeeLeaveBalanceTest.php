<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmployeeLeaveBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.hrm.leave_api_base_url' => 'https://hrm-leave.test',
            'services.hrm.leave_api_token' => 'test-token',
        ]);
    }

    public function test_returns_leave_balance_from_hrm(): void
    {
        Http::fake([
            'https://hrm-leave.test/api/v1/leave/balances*' => Http::response([
                'data' => [
                    'pool_days' => 12,
                    'used_days' => 6.4,
                    'available_days' => 5.6,
                    'usage_percent' => 53.3,
                    'breakdown' => [],
                ],
            ], 200),
        ]);

        $user = User::factory()->create([
            'hrm_employee_uuid' => 'emp-uuid-1',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->getJson('/api/attendance/leave-balance')
            ->assertOk()
            ->assertJsonPath('data.available_days', 5.6);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://hrm-leave.test/api/v1/leave/balances')
                && $request['employee_uuid'] === 'emp-uuid-1';
        });
    }

    public function test_requires_hrm_employee_link(): void
    {
        $user = User::factory()->create([
            'hrm_employee_uuid' => null,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->getJson('/api/attendance/leave-balance')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Tài khoản chưa liên kết nhân sự HRM.']);
    }
}
