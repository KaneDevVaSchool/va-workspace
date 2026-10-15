<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Trang «Duyệt bảng công» — backend Workspace gọi API v1 HRM thay mặt Trưởng đơn vị đang đăng nhập.
 */
class EmployeeTimesheetApprovalTest extends TestCase
{
    use RefreshDatabase;

    private const UUID = '9b2f3c4d-1111-4222-8333-944455556666';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.hrm.api_base_url' => 'https://hrm.test',
            'services.hrm.api_token' => 'test-token',
        ]);
    }

    public function test_lists_pending_timesheets_for_logged_in_unit_head(): void
    {
        Http::fake([
            'https://hrm.test/api/v1/attendance/timesheet-approvals*' => Http::response([
                'data' => [['uuid' => self::UUID, 'status' => 'pending_unit_head']],
            ]),
        ]);

        $this->actingAs($this->unitHead())
            ->getJson('/api/attendance/timesheet-approvals')
            ->assertOk()
            ->assertJsonPath('data.0.uuid', self::UUID);

        // Danh tính lấy từ tài khoản đăng nhập, không nhận từ trình duyệt.
        Http::assertSent(fn (Request $request) => $request['employee_uuid'] === 'emp-head');
    }

    public function test_client_cannot_override_employee_uuid(): void
    {
        Http::fake(['https://hrm.test/*' => Http::response(['data' => []])]);

        $this->actingAs($this->unitHead())
            ->getJson('/api/attendance/timesheet-approvals?employee_uuid=someone-else')
            ->assertOk();

        Http::assertSent(fn (Request $request) => $request['employee_uuid'] === 'emp-head');
    }

    public function test_count_is_cached_and_reset_after_decision(): void
    {
        Http::fake([
            'https://hrm.test/api/v1/attendance/timesheet-approvals/'.self::UUID.'/approve' => Http::response([
                'data' => ['uuid' => self::UUID, 'status' => 'pending_hcns'],
            ]),
            'https://hrm.test/api/v1/attendance/timesheet-approvals*' => Http::sequence()
                ->push(['data' => [['uuid' => self::UUID]]])
                ->push(['data' => []]),
        ]);
        $user = $this->unitHead();

        $this->actingAs($user)->getJson('/api/attendance/timesheet-approvals/count')->assertJsonPath('count', 1);
        $this->actingAs($user)->getJson('/api/attendance/timesheet-approvals/count')->assertJsonPath('count', 1);

        $this->actingAs($user)
            ->postJson('/api/attendance/timesheet-approvals/'.self::UUID.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_hcns');

        $this->actingAs($user)->getJson('/api/attendance/timesheet-approvals/count')->assertJsonPath('count', 0);
    }

    public function test_reject_requires_reason(): void
    {
        Http::fake();

        $this->actingAs($this->unitHead())
            ->postJson('/api/attendance/timesheet-approvals/'.self::UUID.'/reject', ['reason' => 'ngắn'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        Http::assertNothingSent();
    }

    public function test_hrm_business_error_is_returned_as_message(): void
    {
        Http::fake([
            'https://hrm.test/*' => Http::response([
                'error' => ['code' => 'HTTP_ERROR', 'message' => 'Bạn không phải Trưởng đơn vị được gán cho phòng này.'],
            ], 403),
        ]);

        $this->actingAs($this->unitHead())
            ->postJson('/api/attendance/timesheet-approvals/'.self::UUID.'/approve')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Bạn không phải Trưởng đơn vị được gán cho phòng này.');
    }

    public function test_requires_hrm_employee_link(): void
    {
        Http::fake();
        $user = User::factory()->create(['hrm_employee_uuid' => null, 'status' => 'active']);

        $this->actingAs($user)
            ->getJson('/api/attendance/timesheet-approvals')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tài khoản chưa liên kết nhân sự HRM.');

        $this->actingAs($user)
            ->getJson('/api/attendance/timesheet-approvals/count')
            ->assertOk()
            ->assertJsonPath('count', 0);

        Http::assertNothingSent();
    }

    private function unitHead(): User
    {
        return User::factory()->create([
            'hrm_employee_uuid' => 'emp-head',
            'status' => 'active',
        ]);
    }
}
