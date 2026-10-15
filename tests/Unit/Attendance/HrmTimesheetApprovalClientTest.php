<?php

namespace Tests\Unit\Attendance;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;
use Modules\Identity\App\Hrm\Services\HrmApiClient;
use Tests\TestCase;

/**
 * HrmApiClient — nhóm hàm duyệt bảng chấm công (API v1 attendance/timesheet-approvals).
 */
class HrmTimesheetApprovalClientTest extends TestCase
{
    private const UUID = '9b2f3c4d-1111-4222-8333-944455556666';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.hrm.api_base_url' => 'https://hrm.test',
            'services.hrm.api_token' => 'main-token',
            'services.hrm.timesheet_api_token' => null,
        ]);
    }

    public function test_lists_pending_with_main_token_by_default(): void
    {
        Http::fake([
            'https://hrm.test/api/v1/attendance/timesheet-approvals*' => Http::response([
                'data' => [['uuid' => self::UUID, 'status' => 'pending_unit_head']],
                'meta' => ['count' => 1],
            ]),
        ]);

        $items = app(HrmApiClient::class)->listTimesheetApprovals('emp-1');

        $this->assertSame(self::UUID, $items[0]['uuid']);
        Http::assertSent(fn (Request $request) => $request['employee_uuid'] === 'emp-1'
            && $request['scope'] === 'pending'
            && $request->hasHeader('Authorization', 'Bearer main-token'));
    }

    public function test_uses_dedicated_timesheet_token_when_configured(): void
    {
        config(['services.hrm.timesheet_api_token' => 'timesheet-token']);
        Http::fake(['https://hrm.test/*' => Http::response(['data' => []])]);

        app(HrmApiClient::class)->listTimesheetApprovals('emp-1', 'history');

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer timesheet-token')
            && $request['scope'] === 'history');
    }

    public function test_reject_sends_reason_and_returns_data(): void
    {
        Http::fake([
            'https://hrm.test/api/v1/attendance/timesheet-approvals/'.self::UUID.'/reject' => Http::response([
                'data' => ['uuid' => self::UUID, 'status' => 'rejected'],
            ]),
        ]);

        $result = app(HrmApiClient::class)->decideTimesheetApproval(self::UUID, 'emp-1', false, 'Sai công ngày 3');

        $this->assertSame('rejected', $result['status']);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request['employee_uuid'] === 'emp-1'
            && $request['reason'] === 'Sai công ngày 3');
    }

    public function test_validation_error_is_user_facing_with_first_message(): void
    {
        Http::fake([
            'https://hrm.test/*' => Http::response([
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Dữ liệu không hợp lệ.',
                    'details' => ['errors' => ['confirmation' => ['Bảng chấm công đã được xử lý hoặc đã gửi lại — thao tác này không còn hiệu lực.']]],
                ],
            ], 422),
        ]);

        try {
            app(HrmApiClient::class)->decideTimesheetApproval(self::UUID, 'emp-1', true);
            $this->fail('Phải ném HrmApiUnavailable.');
        } catch (HrmApiUnavailable $e) {
            $this->assertFalse($e->isConnectionFailure());
            $this->assertStringContainsString('đã được xử lý', $e->getMessage());
        }
    }

    public function test_missing_ability_has_clear_message(): void
    {
        Http::fake([
            'https://hrm.test/*' => Http::response(['error' => ['code' => 'MISSING_ABILITY', 'message' => 'x']], 403),
        ]);

        $this->expectException(HrmApiUnavailable::class);
        $this->expectExceptionMessage('chưa được cấp quyền duyệt bảng chấm công');

        app(HrmApiClient::class)->listTimesheetApprovals('emp-1');
    }

    public function test_not_unit_head_message_is_passed_through(): void
    {
        Http::fake([
            'https://hrm.test/*' => Http::response([
                'error' => ['code' => 'HTTP_ERROR', 'message' => 'Bạn không phải Trưởng đơn vị được gán cho phòng này.'],
            ], 403),
        ]);

        $this->expectException(HrmApiUnavailable::class);
        $this->expectExceptionMessage('Bạn không phải Trưởng đơn vị');

        app(HrmApiClient::class)->decideTimesheetApproval(self::UUID, 'emp-1', true);
    }

    public function test_detail_returns_null_when_not_found(): void
    {
        Http::fake(['https://hrm.test/*' => Http::response(['error' => ['code' => 'NOT_FOUND']], 404)]);

        $this->assertNull(app(HrmApiClient::class)->getTimesheetApproval(self::UUID, 'emp-1'));
    }
}
