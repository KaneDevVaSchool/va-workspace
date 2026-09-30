<?php

namespace Modules\Identity\App\Hrm\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;
use Modules\Identity\App\Hrm\Services\HrmApiClient;
use Modules\Identity\App\Hrm\Services\HrmEmployeeSyncService;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;

/**
 * Xử lý webhook employee.updated — changed_fields chỉ có tên cột (không có
 * giá trị), nên luôn gọi lại API lấy full state mới rồi ghi đè (idempotent
 * theo thiết kế, không áp diff thủ công).
 */
class ProcessEmployeeUpdatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @param list<string> $changedFields */
    public function __construct(
        public readonly string $employeeUuid,
        public readonly array $changedFields,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(
        UserRepositoryInterface $users,
        HrmApiClient $hrmApi,
        HrmEmployeeSyncService $sync,
    ): void {
        $user = $users->findByHrmEmployeeUuid($this->employeeUuid);

        if ($user === null) {
            Log::info('hrm_webhook.employee_updated.no_matching_user', [
                'employee_uuid' => $this->employeeUuid,
            ]);

            return;
        }

        try {
            $employee = $hrmApi->getEmployee($this->employeeUuid);
        } catch (HrmApiUnavailable $e) {
            // Ném lại để Laravel queue retry theo backoff() — HRM đang lỗi tạm thời.
            throw $e;
        }

        if ($employee !== null) {
            $sync->applyToUser($user, $employee);
        }
    }
}
