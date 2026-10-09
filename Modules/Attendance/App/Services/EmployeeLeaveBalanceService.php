<?php

namespace Modules\Attendance\App\Services;

use App\Models\User;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;
use Modules\Identity\App\Hrm\Services\HrmApiClient;

/**
 * Tổng quan số dư phép năm — proxy GET /api/v1/leave/balances (portal HRM).
 */
class EmployeeLeaveBalanceService
{
    public function __construct(
        private readonly HrmApiClient $hrmApi,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws HrmApiUnavailable
     */
    public function overviewForUser(User $user, ?int $year = null): array
    {
        $employeeUuid = $user->hrm_employee_uuid;
        if (! filled($employeeUuid)) {
            throw HrmApiUnavailable::userFacing('Tài khoản chưa liên kết nhân sự HRM.');
        }

        $year = $year ?? (int) now()->format('Y');

        return $this->hrmApi->getLeaveBalance((string) $employeeUuid, $year);
    }
}
