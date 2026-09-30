<?php

namespace Modules\Identity\App\Hrm\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;

/**
 * Xử lý webhook employee.terminated — tự động khoá tài khoản ngay
 * (status = inactive), theo quyết định: HRM báo nghỉ việc thì chặn đăng
 * nhập workspace ngay lập tức, không chờ admin thao tác tay.
 */
class ProcessEmployeeTerminatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly string $employeeUuid,
        public readonly ?string $terminatedAt,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(UserRepositoryInterface $users): void
    {
        $user = $users->findByHrmEmployeeUuid($this->employeeUuid);

        if ($user === null) {
            Log::info('hrm_webhook.employee_terminated.no_matching_user', [
                'employee_uuid' => $this->employeeUuid,
            ]);

            return;
        }

        $users->update($user, [
            'status' => 'inactive',
            'hrm_terminated_at' => $this->terminatedAt ?? now(),
        ]);
    }
}
