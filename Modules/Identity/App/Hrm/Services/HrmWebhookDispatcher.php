<?php

namespace Modules\Identity\App\Hrm\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Identity\App\Hrm\Jobs\ProcessEmployeeTerminatedJob;
use Modules\Identity\App\Hrm\Jobs\ProcessEmployeeTransferredJob;
use Modules\Identity\App\Hrm\Jobs\ProcessEmployeeUpdatedJob;
use Modules\Identity\App\Hrm\Jobs\ProcessOrgUnitChangedJob;
use Modules\Identity\App\Models\HrmWebhookDelivery;

/**
 * Nhận event webhook đã xác thực chữ ký (VerifyHrmWebhookSignature) -> check
 * idempotency (delivery_id) -> dispatch đúng Job. Không gọi API HRM hay ghi
 * DB nghiệp vụ ở đây — chỉ bookkeeping + routing, xử lý nặng nằm ở Job (vì
 * HRM retry nếu response không 2xx, controller phải trả 2xx nhanh).
 */
class HrmWebhookDispatcher
{
    /** @param array<string, mixed> $payload */
    public function dispatch(?string $event, ?string $deliveryId, array $payload): void
    {
        if ($event === null || $deliveryId === null) {
            Log::warning('hrm_webhook.missing_headers', ['event' => $event, 'delivery_id' => $deliveryId]);

            return;
        }

        $lock = Cache::lock("hrm-webhook:{$deliveryId}", 10);

        if (! $lock->get()) {
            return;
        }

        try {
            if (HrmWebhookDelivery::query()->where('delivery_id', $deliveryId)->exists()) {
                return;
            }

            HrmWebhookDelivery::query()->create([
                'delivery_id' => $deliveryId,
                'event' => $event,
                'processed_at' => now(),
            ]);

            $this->routeToJob($event, $payload);
        } finally {
            $lock->release();
        }
    }

    /** @param array<string, mixed> $payload */
    private function routeToJob(string $event, array $payload): void
    {
        match ($event) {
            'employee.updated' => ProcessEmployeeUpdatedJob::dispatch(
                (string) ($payload['employee_uuid'] ?? ''),
                (array) ($payload['changed_fields'] ?? []),
            ),
            'employee.transferred' => ProcessEmployeeTransferredJob::dispatch(
                (string) ($payload['employee_uuid'] ?? ''),
            ),
            'employee.terminated' => ProcessEmployeeTerminatedJob::dispatch(
                (string) ($payload['employee_uuid'] ?? ''),
                $payload['terminated_at'] ?? null,
            ),
            'org_unit.changed' => ProcessOrgUnitChangedJob::dispatch(
                (string) ($payload['org_uuid'] ?? ''),
                (string) ($payload['change_type'] ?? ''),
            ),
            default => Log::info('hrm_webhook.unknown_event', ['event' => $event]),
        };
    }
}
