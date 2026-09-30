<?php

namespace Modules\Identity\App\Hrm\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Identity\App\Models\Company;
use Modules\Identity\App\Repositories\Contracts\DepartmentRepositoryInterface;
use Modules\Identity\App\Hrm\Services\HrmApiClient;

/**
 * Xử lý webhook org_unit.changed — chỉ cập nhật Department đã map sẵn tới
 * OrgUnit này (qua hrm_org_unit_uuid, gán tay qua UI Department). KHÔNG tự
 * tạo Department mới khi change_type=created: Department gắn với permission
 * scope, tạo tự động có thể sinh phòng ban rác chưa ai dùng — admin tự cân
 * nhắc tạo tay + gán hrm_org_unit_uuid khi cần.
 */
class ProcessOrgUnitChangedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly string $orgUuid,
        public readonly string $changeType,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(DepartmentRepositoryInterface $departments, HrmApiClient $hrmApi): void
    {
        $department = $departments->findByHrmOrgUnitUuid($this->orgUuid);

        if ($department === null) {
            Log::info('hrm_webhook.org_unit_changed.no_matching_department', [
                'org_uuid' => $this->orgUuid,
                'change_type' => $this->changeType,
            ]);

            return;
        }

        $orgUnit = $hrmApi->getOrgUnit($this->orgUuid);

        if ($orgUnit === null) {
            return;
        }

        $company = $this->resolveCompany($orgUnit['company'] ?? null);

        $department->fill(array_filter([
            'name' => $orgUnit['name'] ?? null,
            'external_code' => $orgUnit['code'] ?? null,
            'company_id' => $company?->id,
        ], fn ($value) => $value !== null))->save();
    }

    /** @param array<string, mixed>|null $companyPayload */
    private function resolveCompany(?array $companyPayload): ?Company
    {
        if ($companyPayload === null || ! isset($companyPayload['uuid'])) {
            return null;
        }

        return Company::query()->updateOrCreate(
            ['hrm_uuid' => $companyPayload['uuid']],
            [
                'code' => $companyPayload['code'] ?? $companyPayload['uuid'],
                'name' => $companyPayload['name'] ?? $companyPayload['code'] ?? $companyPayload['uuid'],
            ],
        );
    }
}
