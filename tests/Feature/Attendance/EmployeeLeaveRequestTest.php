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

    public function test_downloads_leave_form_pdf_when_approved(): void
    {
        config(['database.connections.hrm.database' => null]);

        $uuid = '11111111-1111-4111-8111-111111111111';
        Http::fake([
            'https://hrm-leave.test/api/v1/employees/'.$uuid => Http::response([
                'data' => ['uuid' => $uuid, 'full_name' => 'Phạm Quang Thái'],
            ]),
            'https://hrm-leave.test/api/v1/leave/requests*' => Http::response([
                'data' => [[
                    'uuid' => $uuid,
                    'id' => 8,
                    'status' => 'approved',
                    'employee_name' => 'Phạm Quang Thái',
                    'employee_code' => 'VA011276',
                    'leave_type_code' => 'PN',
                    'leave_type_name' => 'Nghỉ phép năm',
                    'mode' => 'hour',
                    'date_from' => '2026-10-10',
                    'date_to' => '2026-10-10',
                    'time_from' => '07:30:00',
                    'time_to' => '11:30:00',
                    'total_days' => 0.5,
                    'reason' => 'Việc gia đình',
                    'submitted_at' => '2026-10-07T02:00:00Z',
                    'decided_at' => '2026-10-19T02:00:00Z',
                    'approver' => ['name' => 'Nguyễn Viết Hùng', 'job_title' => 'Trưởng đơn vị'],
                ]],
            ]),
        ]);

        $user = User::factory()->create([
            'hrm_employee_uuid' => $uuid,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->get('/api/attendance/leave-requests/'.$uuid.'/form');

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('NP-2610-0008', urldecode((string) $response->headers->get('content-disposition')));
    }

    public function test_refuses_leave_form_when_not_approved(): void
    {
        config(['database.connections.hrm.database' => null]);

        $uuid = '22222222-2222-4222-8222-222222222222';
        Http::fake([
            'https://hrm-leave.test/api/v1/employees/'.$uuid => Http::response([
                'data' => ['uuid' => $uuid],
            ]),
            'https://hrm-leave.test/api/v1/leave/requests*' => Http::response([
                'data' => [[
                    'uuid' => $uuid,
                    'status' => 'pending',
                    'date_from' => '2026-10-10',
                    'leave_type_code' => 'PN',
                    'leave_type_name' => 'Phép năm',
                ]],
            ]),
        ]);

        $user = User::factory()->create([
            'hrm_employee_uuid' => $uuid,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->getJson('/api/attendance/leave-requests/'.$uuid.'/form')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Chỉ xuất được đơn đã được duyệt.']);
    }
}
