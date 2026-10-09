<?php

namespace Modules\Attendance\App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;
use Modules\Identity\App\Hrm\Services\HrmApiClient;

/**
 * Gửi đơn nghỉ từ workspace → HRM Public API (POST /api/v1/leave/requests).
 */
class EmployeeLeaveSubmissionService
{
    public function __construct(
        private readonly HrmApiClient $hrmApi,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $periods
     * @param  list<UploadedFile>  $attachments
     * @return list<array<string, mixed>>
     *
     * @throws HrmApiUnavailable
     */
    public function submitForUser(
        User $user,
        int $leaveTypeId,
        string $reason,
        array $periods,
        ?string $documentLink,
        ?string $approverEmployeeUuid,
        ?string $followerEmployeeUuid,
        array $attachments = [],
    ): array {
        $employeeUuid = $user->hrm_employee_uuid;
        if (! filled($employeeUuid)) {
            throw new HrmApiUnavailable('Tài khoản chưa liên kết nhân sự HRM.');
        }

        if ($periods === []) {
            throw new HrmApiUnavailable('Chọn ít nhất một khoảng nghỉ.');
        }

        $batchRef = (string) Str::uuid();
        $created = [];

        foreach ($periods as $index => $period) {
            $fields = [
                'employee_uuid' => (string) $employeeUuid,
                'leave_type_id' => $leaveTypeId,
                'mode' => (string) ($period['mode'] ?? 'day'),
                'date_from' => (string) ($period['date_from'] ?? ''),
                'date_to' => filled($period['date_to'] ?? null) ? (string) $period['date_to'] : null,
                'session' => filled($period['session'] ?? null) ? (string) $period['session'] : null,
                'time_from' => filled($period['time_from'] ?? null) ? (string) $period['time_from'] : null,
                'time_to' => filled($period['time_to'] ?? null) ? (string) $period['time_to'] : null,
                'reason' => $reason,
                'document_link' => filled($documentLink) ? $documentLink : null,
                'approver_employee_uuid' => filled($approverEmployeeUuid) ? $approverEmployeeUuid : null,
                'follower_employee_uuid' => filled($followerEmployeeUuid) ? $followerEmployeeUuid : null,
                'external_ref' => $batchRef.'-'.($index + 1),
            ];

            $files = $index === 0 ? $attachments : [];

            $created[] = $this->hrmApi->createLeaveRequest($fields, $files);
        }

        return $created;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws HrmApiUnavailable
     */
    public function recentForUser(User $user, int $limit = 20): array
    {
        $employeeUuid = $user->hrm_employee_uuid;
        if (! filled($employeeUuid)) {
            return [];
        }

        $page = $this->hrmApi->listLeaveRequests((string) $employeeUuid, min(50, max(1, $limit)));

        return $page['items'];
    }
}
