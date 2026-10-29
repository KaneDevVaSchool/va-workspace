<?php

namespace Modules\Attendance\App\Services;

use App\Models\User;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;
use Modules\Identity\App\Hrm\Services\HrmApiClient;

/**
 * Cấu hình vùng chấm công (chi nhánh HRM) cho nhân viên đang đăng nhập.
 */
class EmployeeAttendanceCheckInContextService
{
    public function __construct(
        private readonly HrmApiClient $hrmApi,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws HrmApiUnavailable
     */
    public function forUser(User $user): array
    {
        $uuid = trim((string) $user->hrm_employee_uuid);
        if ($uuid === '') {
            throw HrmApiUnavailable::userFacing('Tài khoản chưa liên kết nhân sự HRM.');
        }

        return $this->hrmApi->getAttendanceCheckInContext($uuid);
    }
}
