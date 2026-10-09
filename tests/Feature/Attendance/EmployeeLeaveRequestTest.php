<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmployeeLeaveRequestTest extends TestCase
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

    public function test_submits_leave_request_to_hrm(): void
    {
        Http::fake([
            'https://hrm-leave.test/api/v1/leave/requests' => Http::response([
                'data' => [
                    'uuid' => 'req-1',
                    'status' => 'pending',
                    'total_days' => 1,
                ],
            ], 201),
        ]);

        $user = User::factory()->create([
            'hrm_employee_uuid' => 'emp-uuid-1',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->postJson('/api/attendance/leave-requests', [
                'leave_type_id' => 2,
                'reason' => 'Việc riêng',
                'periods' => [
                    [
                        'mode' => 'day',
                        'date_from' => now()->addDay()->toDateString(),
                        'date_to' => now()->addDay()->toDateString(),
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.0.uuid', 'req-1');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://hrm-leave.test/api/v1/leave/requests'
                && $request['employee_uuid'] === 'emp-uuid-1'
                && $request['leave_type_id'] === 2;
        });
    }

    public function test_requires_hrm_employee_link(): void
    {
        $user = User::factory()->create([
            'hrm_employee_uuid' => null,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->postJson('/api/attendance/leave-requests', [
                'leave_type_id' => 1,
                'reason' => 'Test',
                'periods' => [
                    ['mode' => 'day', 'date_from' => now()->toDateString()],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Tài khoản chưa liên kết nhân sự HRM.']);
    }
}
