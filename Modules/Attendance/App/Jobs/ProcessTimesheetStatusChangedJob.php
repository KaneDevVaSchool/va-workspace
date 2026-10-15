<?php

namespace Modules\Attendance\App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Attendance\App\Services\EmployeeTimesheetApprovalService;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;
use Modules\Identity\App\Services\NotificationService;

/**
 * Webhook attendance.timesheet.status_changed (VA-HRM) → thông báo chuông + web push.
 *
 * - kind=request: người cần xử lý bước kế (notify.approver_employee_uuids). Bước Trưởng đơn vị
 *   mở trang «Duyệt bảng công» trên Workspace; bước HCNS / PP mở thẳng bảng trên HRM.
 * - kind=rejected | finalized: người gửi + Admin đơn vị (+ Trưởng đơn vị) — mở bảng trên HRM.
 *
 * Nhân sự chưa có tài khoản Workspace (chưa đăng nhập SSO lần nào) thì bỏ qua — email HRM vẫn gửi.
 */
class ProcessTimesheetStatusChangedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public readonly array $payload) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(UserRepositoryInterface $users, NotificationService $notifications): void
    {
        $kind = (string) ($this->payload['kind'] ?? '');
        $confirmationUuid = (string) ($this->payload['confirmation_uuid'] ?? '');
        if ($confirmationUuid === '' || ! in_array($kind, ['request', 'rejected', 'finalized'], true)) {
            Log::info('hrm_webhook.timesheet.ignored', ['kind' => $kind]);

            return;
        }

        $subject = trim(implode(' · ', array_filter([
            (string) data_get($this->payload, 'org_unit.name', ''),
            (string) data_get($this->payload, 'period.label', ''),
        ])));
        $hrmUrl = (string) ($this->payload['hrm_url'] ?? '');
        $data = [
            'confirmation_uuid' => $confirmationUuid,
            'status' => $this->payload['status'] ?? null,
            'push_tag' => 'hrm-timesheet-'.$confirmationUuid,
        ];

        if ($kind === 'request') {
            $isUnitHeadStep = ($this->payload['stage'] ?? null) === 'unit_head';
            $url = $isUnitHeadStep
                ? '/attendance/timesheet-approvals?confirmation='.$confirmationUuid
                : $hrmUrl;

            $this->notifyEmployees(
                $users,
                $notifications,
                (array) data_get($this->payload, 'notify.approver_employee_uuids', []),
                NotificationService::TYPE_HRM_TIMESHEET_PENDING,
                $isUnitHeadStep ? 'Bảng chấm công chờ anh/chị duyệt' : 'Bảng chấm công chờ xử lý',
                $subject,
                $url !== '' ? $url : null,
                $data,
                forgetPendingCount: true,
            );

            return;
        }

        $reason = trim((string) ($this->payload['reason'] ?? ''));
        $this->notifyEmployees(
            $users,
            $notifications,
            (array) data_get($this->payload, 'notify.owner_employee_uuids', []),
            $kind === 'rejected'
                ? NotificationService::TYPE_HRM_TIMESHEET_REJECTED
                : NotificationService::TYPE_HRM_TIMESHEET_FINALIZED,
            $kind === 'rejected' ? 'Bảng chấm công bị trả về' : 'Bảng chấm công đã duyệt cuối',
            $kind === 'rejected' && $reason !== ''
                ? mb_strimwidth($subject.' — Lý do: '.$reason, 0, 480, '…')
                : $subject,
            $hrmUrl !== '' ? $hrmUrl : null,
            $data,
        );
    }

    /**
     * @param  list<mixed>  $employeeUuids
     * @param  array<string, mixed>  $data
     */
    private function notifyEmployees(
        UserRepositoryInterface $users,
        NotificationService $notifications,
        array $employeeUuids,
        string $type,
        string $title,
        ?string $body,
        ?string $url,
        array $data,
        bool $forgetPendingCount = false,
    ): void {
        foreach (array_unique(array_filter(array_map('strval', $employeeUuids))) as $employeeUuid) {
            $user = $users->findByHrmEmployeeUuid($employeeUuid);
            if ($user === null) {
                continue;
            }

            if ($forgetPendingCount) {
                EmployeeTimesheetApprovalService::forgetCount((int) $user->id);
            }

            $notifications->notifySystem($user, $type, $title, $body, $url, $data);
        }
    }
}
