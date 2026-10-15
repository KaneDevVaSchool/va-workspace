<?php

namespace Tests\Unit\Attendance;

use App\Models\User;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Modules\Attendance\App\Jobs\ProcessTimesheetStatusChangedJob;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;
use Modules\Identity\App\Services\NotificationService;
use Tests\TestCase;

/**
 * Webhook attendance.timesheet.status_changed → thông báo đúng người, đúng link.
 */
class ProcessTimesheetStatusChangedJobTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const CONFIRMATION = '9b2f3c4d-1111-4222-8333-944455556666';

    public function test_unit_head_request_links_to_workspace_approval_page(): void
    {
        $head = $this->user(7);
        $users = Mockery::mock(UserRepositoryInterface::class);
        $users->shouldReceive('findByHrmEmployeeUuid')->with('emp-head')->andReturn($head);
        $users->shouldReceive('findByHrmEmployeeUuid')->with('emp-no-account')->andReturnNull();

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('notifySystem')
            ->once()
            ->withArgs(fn ($recipient, $type, $title, $body, $url) => $recipient === $head
                && $type === NotificationService::TYPE_HRM_TIMESHEET_PENDING
                && $url === '/attendance/timesheet-approvals?confirmation='.self::CONFIRMATION
                && $body === 'Công nghệ · Tháng 10/2026');

        (new ProcessTimesheetStatusChangedJob($this->payload('request', 'unit_head', [
            'approver_employee_uuids' => ['emp-head', 'emp-no-account'],
            'owner_employee_uuids' => [],
        ])))->handle($users, $notifications);
    }

    public function test_hcns_request_links_to_hrm(): void
    {
        $hr = $this->user(9);
        $users = Mockery::mock(UserRepositoryInterface::class);
        $users->shouldReceive('findByHrmEmployeeUuid')->with('emp-hr')->andReturn($hr);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('notifySystem')
            ->once()
            ->withArgs(fn ($recipient, $type, $title, $body, $url) => $recipient === $hr
                && $url === 'https://hrm.test/attendance/timesheet/board?period=p&org_unit_id=1');

        (new ProcessTimesheetStatusChangedJob($this->payload('request', 'hcns', [
            'approver_employee_uuids' => ['emp-hr'],
            'owner_employee_uuids' => [],
        ])))->handle($users, $notifications);
    }

    public function test_rejection_notifies_owners_with_reason(): void
    {
        $admin = $this->user(11);
        $users = Mockery::mock(UserRepositoryInterface::class);
        $users->shouldReceive('findByHrmEmployeeUuid')->with('emp-admin')->andReturn($admin);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('notifySystem')
            ->once()
            ->withArgs(fn ($recipient, $type, $title, $body) => $recipient === $admin
                && $type === NotificationService::TYPE_HRM_TIMESHEET_REJECTED
                && str_contains((string) $body, 'Lý do: Thiếu công ngày 6'));

        (new ProcessTimesheetStatusChangedJob($this->payload('rejected', 'hcns', [
            'approver_employee_uuids' => [],
            'owner_employee_uuids' => ['emp-admin'],
        ], 'Thiếu công ngày 6')))->handle($users, $notifications);
    }

    public function test_unknown_kind_is_ignored(): void
    {
        $users = Mockery::mock(UserRepositoryInterface::class);
        $users->shouldNotReceive('findByHrmEmployeeUuid');
        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldNotReceive('notifySystem');

        (new ProcessTimesheetStatusChangedJob(['kind' => 'other', 'confirmation_uuid' => self::CONFIRMATION]))
            ->handle($users, $notifications);
    }

    private function user(int $id): User
    {
        $user = new User;
        $user->forceFill(['id' => $id, 'status' => 'active']);

        return $user;
    }

    /**
     * @param  array<string, list<string>>  $notify
     * @return array<string, mixed>
     */
    private function payload(string $kind, string $stage, array $notify, ?string $reason = null): array
    {
        return [
            'confirmation_uuid' => self::CONFIRMATION,
            'kind' => $kind,
            'stage' => $stage,
            'status' => 'pending_unit_head',
            'reason' => $reason,
            'period' => ['label' => 'Tháng 10/2026'],
            'org_unit' => ['name' => 'Công nghệ'],
            'notify' => $notify,
            'hrm_url' => 'https://hrm.test/attendance/timesheet/board?period=p&org_unit_id=1',
        ];
    }
}
