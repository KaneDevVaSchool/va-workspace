<?php

namespace Modules\Identity\App\Hrm\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;
use Modules\Identity\App\Models\Company;
use Modules\Identity\App\Models\Department;

/**
 * Đồng bộ OrgUnit VA-HRM → bảng departments (phẳng) để dropdown gán phòng ban
 * và tổng hợp workspace dùng cùng department_id integer.
 */
class HrmDepartmentSyncService
{
    /** Các type HRM mà nhân sự có thể thuộc (bỏ headquarter — thường không gán user). */
    private const ASSIGNABLE_ORG_UNIT_TYPES = ['department', 'unit', 'branch'];

    public function __construct(private readonly HrmApiClient $hrmApi) {}

    public static function isConfigured(): bool
    {
        return filled(config('services.hrm.api_base_url'))
            && filled(config('services.hrm.api_token'));
    }

    /**
     * Kéo toàn bộ org-units từ HRM và upsert departments local. Không làm gì
     * khi chưa cấu hình HRM; lỗi API được log, không chặn trang (fallback DB local).
     */
    public function syncDepartmentsFromHrm(): void
    {
        if (! self::isConfigured()) {
            return;
        }

        try {
            $orgUnits = $this->hrmApi->listAllOrgUnits();
        } catch (HrmApiUnavailable $e) {
            Log::warning('hrm.department_sync.failed', ['message' => $e->getMessage()]);

            return;
        }

        $this->syncDepartmentsFromRecords($orgUnits);
    }

    /**
     * Upsert phòng ban workspace từ các dòng org-unit đã đọc ở DB HRM
     * (cùng shape với OrgUnitResource). Không gọi API.
     *
     * @param  iterable<int, array<string, mixed>>  $orgUnits
     */
    public function syncDepartmentsFromRecords(iterable $orgUnits): void
    {
        DB::transaction(function () use ($orgUnits): void {
            foreach ($orgUnits as $orgUnit) {
                if (! is_array($orgUnit) || ! isset($orgUnit['uuid'])) {
                    continue;
                }

                $type = (string) ($orgUnit['type'] ?? '');
                if ($type !== '' && ! in_array($type, self::ASSIGNABLE_ORG_UNIT_TYPES, true)) {
                    continue;
                }

                $this->upsertDepartment($orgUnit);
            }
        });
    }

    /**
     * Cập nhật trưởng đơn vị (manager) từ GET /org-units/{uuid} — list index
     * không trả manager. TTL cache giống bulk sync nhân viên.
     */
    public function syncDepartmentManagersFromHrm(): void
    {
        if (! self::isConfigured()) {
            return;
        }

        $ttlSeconds = max(60, (int) config('services.hrm.department_manager_sync_ttl', 900));
        $doneKey = 'hrm:department_manager_sync:done';

        if (Cache::has($doneKey)) {
            return;
        }

        $lock = Cache::lock('hrm:department_manager_sync:lock', min(600, $ttlSeconds));
        if (! $lock->get()) {
            return;
        }

        try {
            if (Cache::has($doneKey)) {
                return;
            }

            $this->runManagerSync();
            Cache::put($doneKey, true, $ttlSeconds);
        } finally {
            $lock->release();
        }
    }

    private function runManagerSync(): void
    {
        $departments = Department::query()
            ->whereNotNull('hrm_org_unit_uuid')
            ->get(['id', 'hrm_org_unit_uuid', 'hrm_manager_employee_uuid', 'hrm_manager_name', 'hrm_manager_email']);

        foreach ($departments as $department) {
            $uuid = (string) $department->hrm_org_unit_uuid;
            if ($uuid === '') {
                continue;
            }

            try {
                $orgUnit = $this->hrmApi->getOrgUnit($uuid);
            } catch (HrmApiUnavailable $e) {
                Log::warning('hrm.department_manager_sync.failed', [
                    'org_unit_uuid' => $uuid,
                    'message' => $e->getMessage(),
                ]);

                return;
            }

            if ($orgUnit === null) {
                continue;
            }

            $this->applyManagerFromOrgUnit($department, $orgUnit);
        }
    }

    /**
     * @param  array<string, mixed>  $orgUnit  OrgUnitResource (detail)
     */
    public function applyManagerFromOrgUnit(Department $department, array $orgUnit): void
    {
        $manager = $orgUnit['manager'] ?? null;
        if (! is_array($manager) || ! isset($manager['uuid'])) {
            if ($department->hrm_manager_employee_uuid !== null
                || $department->hrm_manager_name !== null
                || $department->hrm_manager_email !== null) {
                $department->forceFill([
                    'hrm_manager_employee_uuid' => null,
                    'hrm_manager_name' => null,
                    'hrm_manager_email' => null,
                ])->save();
            }

            return;
        }

        $employeeUuid = (string) $manager['uuid'];
        $name = trim((string) ($manager['full_name'] ?? ''));
        $email = User::query()->where('hrm_employee_uuid', $employeeUuid)->value('email');

        if ($email === null) {
            try {
                $employee = $this->hrmApi->getEmployee($employeeUuid);
                $email = $employee?->companyEmail;
            } catch (HrmApiUnavailable $e) {
                Log::debug('hrm.department_manager_sync.email_lookup_failed', [
                    'employee_uuid' => $employeeUuid,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $department->forceFill([
            'hrm_manager_employee_uuid' => $employeeUuid,
            'hrm_manager_name' => $name !== '' ? $name : null,
            'hrm_manager_email' => $email,
        ])->save();
    }

    /** @param array<string, mixed> $orgUnit */
    private function upsertDepartment(array $orgUnit): void
    {
        $uuid = (string) $orgUnit['uuid'];
        $name = trim((string) ($orgUnit['name'] ?? $orgUnit['short_name'] ?? $uuid));
        $code = $this->uniqueDepartmentCode($uuid, $orgUnit);
        $isActive = ($orgUnit['status'] ?? 'active') === 'active';
        $company = $this->resolveCompany($orgUnit['company'] ?? null);

        $department = Department::query()->where('hrm_org_unit_uuid', $uuid)->first();

        $attributes = [
            'code' => $code,
            'name' => $name !== '' ? $name : $code,
            'external_code' => $orgUnit['code'] ?? null,
            'is_active' => $isActive,
            'company_id' => $company?->id,
        ];

        if ($department !== null) {
            $department->fill($attributes)->save();

            return;
        }

        Department::query()->create(array_merge($attributes, [
            'hrm_org_unit_uuid' => $uuid,
        ]));
    }

    /** @param array<string, mixed> $orgUnit */
    private function uniqueDepartmentCode(string $uuid, array $orgUnit): string
    {
        $candidate = trim((string) ($orgUnit['code'] ?? ''));
        if ($candidate === '') {
            $candidate = 'HRM-'.strtoupper(substr(str_replace('-', '', $uuid), 0, 12));
        }

        $conflict = Department::query()
            ->where('code', $candidate)
            ->where(function ($query) use ($uuid): void {
                $query->whereNull('hrm_org_unit_uuid')
                    ->orWhere('hrm_org_unit_uuid', '!=', $uuid);
            })
            ->exists();

        if ($conflict) {
            $candidate = $candidate.'-'.substr($uuid, 0, 8);
        }

        return $candidate;
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
