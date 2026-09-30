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
 * Xử lý webhook employee.transferred — gọi lại full getEmployee() thay vì
 * tự suy luận diff từ from_org_uuid/to_org_uuid, tái dùng đúng logic đồng
 * bộ của employee.updated (luôn lấy state mới nhất rồi ghi đè).
 */
class ProcessEmployeeTransferredJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly string $employeeUuid,
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
            Log::info('hrm_webhook.employee_transferred.no_matching_user', [
                'employee_uuid' => $this->employeeUuid,
            ]);

            return;
        }

        $employee = $hrmApi->getEmployee($this->employeeUuid);

        if ($employee !== null) {
            $sync->applyToUser($user, $employee);
        }
    }
}
