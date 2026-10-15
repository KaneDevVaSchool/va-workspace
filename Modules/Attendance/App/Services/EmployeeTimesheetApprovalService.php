<?php

namespace Modules\Attendance\App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;
use Modules\Identity\App\Hrm\Services\HrmApiClient;

/**
 * Trưởng đơn vị duyệt bảng chấm công HRM ngay trên Workspace.
 *
 * Danh tính người duyệt = hrm_employee_uuid của tài khoản đang đăng nhập (đồng bộ qua SSO HRM) —
 * không nhận uuid từ trình duyệt. HRM kiểm lại người đó đang là quản lý trực tiếp của phòng.
 */
class EmployeeTimesheetApprovalService
{
    /** Badge sidebar — cache ngắn để không gọi HRM mỗi lần chuyển trang. */
    private const COUNT_CACHE_SECONDS = 60;

    public function __construct(
        private readonly HrmApiClient $hrmApi,
    ) {}

    /**
     * @return list<array<string, mixed>>
     *
     * @throws HrmApiUnavailable
     */
    public function listForUser(User $user, string $scope = 'pending'): array
    {
        return $this->hrmApi->listTimesheetApprovals($this->employeeUuid($user), $scope);
    }

    public function pendingCount(User $user): int
    {
        if (! filled($user->hrm_employee_uuid)) {
            return 0;
        }

        return (int) Cache::remember(
            self::countCacheKey((int) $user->id),
            self::COUNT_CACHE_SECONDS,
            function () use ($user): int {
                try {
                    return count($this->hrmApi->listTimesheetApprovals((string) $user->hrm_employee_uuid));
                } catch (HrmApiUnavailable $e) {
                    Log::warning('attendance.timesheet_approvals.count_failed', [
                        'user_id' => $user->id,
                        'message' => $e->getMessage(),
                    ]);

                    return 0;
                }
            },
        );
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws HrmApiUnavailable
     */
    public function detailForUser(User $user, string $confirmationUuid): ?array
    {
        return $this->hrmApi->getTimesheetApproval($confirmationUuid, $this->employeeUuid($user));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws HrmApiUnavailable
     */
    public function decideForUser(User $user, string $confirmationUuid, bool $approve, ?string $reason = null): array
    {
        $result = $this->hrmApi->decideTimesheetApproval(
            $confirmationUuid,
            $this->employeeUuid($user),
            $approve,
            $reason,
        );

        self::forgetCount((int) $user->id);

        return $result;
    }

    public static function forgetCount(int $userId): void
    {
        Cache::forget(self::countCacheKey($userId));
    }

    private static function countCacheKey(int $userId): string
    {
        return "attendance:timesheet-approvals:pending-count:{$userId}";
    }

    /** @throws HrmApiUnavailable */
    private function employeeUuid(User $user): string
    {
        $uuid = trim((string) $user->hrm_employee_uuid);
        if ($uuid === '') {
            throw HrmApiUnavailable::userFacing('Tài khoản chưa liên kết nhân sự HRM.');
        }

        return $uuid;
    }
}
