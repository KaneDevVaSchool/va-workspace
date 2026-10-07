<?php

namespace Modules\Identity\App\Hrm\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Identity\App\Hrm\Exceptions\HrmDatabaseUnavailable;
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

    public function __construct(
        private readonly HrmEmployeeDirectory $directory,
    ) {}

    public static function isConfigured(): bool
    {
        return HrmEmployeeDirectory::isConfigured();
    }

    /**
     * Tài khoản chưa có phòng ban Workspace thì gán đúng phòng ban HRM
     * (không gán bộ phận). Không ghi đè phòng ban đã gán tay.
     * Đọc MySQL HRM, không gọi API.
     */
    public function ensureUserDepartment(User $user): void
    {
        if (! HrmEmployeeDirectory::isConfigured()) {
            return;
        }

        if ($user->department_id !== null
            && Department::query()->whereKey($user->department_id)->exists()) {
            return;
        }

        try {
            $orgUnit = $this->directory->primaryDepartmentOrgUnit($user->hrm_employee_uuid, $user->email);
        } catch (HrmDatabaseUnavailable $e) {
            Log::warning('hrm.user_department.ensure_failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        if ($orgUnit === null) {
            return;
        }

        $this->upsertDepartment($orgUnit);

        $department = Department::query()->where('hrm_org_unit_uuid', $orgUnit['uuid'])->first();
        if ($department === null) {
            return;
        }

        $fill = ['department_id' => $department->id];
        $employeeUuid = filled($orgUnit['employee_uuid'] ?? null) ? (string) $orgUnit['employee_uuid'] : null;
        if ($employeeUuid !== null
            && $user->hrm_employee_uuid !== $employeeUuid
            && ! User::query()->where('hrm_employee_uuid', $employeeUuid)->whereKeyNot($user->id)->exists()) {
            $fill['hrm_employee_uuid'] = $employeeUuid;
        }

        $user->forceFill($fill)->save();
    }

    public function syncDepartmentsFromHrm(): void
    {
        if (! self::isConfigured()) {
            return;
        }

        try {
            $orgUnits = $this->directory->assignableOrgUnits();
        } catch (HrmDatabaseUnavailable $e) {
            Log::warning('hrm.department_sync.failed', ['message' => $e->getMessage()]);

            return;
        }

        $this->syncDepartmentsFromRecords($orgUnits);
        $this->applyManagers($orgUnits);
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
     * Cập nhật trưởng đơn vị từ MySQL HRM (org_units.manager_employee_id).
     */
    public function syncDepartmentManagersFromHrm(): void
    {
        if (! self::isConfigured()) {
            return;
        }

        try {
            $this->applyManagers($this->directory->assignableOrgUnits());
        } catch (HrmDatabaseUnavailable $e) {
            Log::warning('hrm.department_manager_sync.failed', ['message' => $e->getMessage()]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $orgUnits
     */
    private function applyManagers(array $orgUnits): void
    {
        foreach ($orgUnits as $orgUnit) {
            $uuid = (string) ($orgUnit['uuid'] ?? '');
            if ($uuid === '') {
                continue;
            }

            $department = Department::query()->where('hrm_org_unit_uuid', $uuid)->first();
            if ($department === null) {
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
        $email = User::query()->where('hrm_employee_uuid', $employeeUuid)->value('email')
            ?? (filled($manager['email'] ?? null) ? (string) $manager['email'] : null);

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

        return Company::upsertFromHrm(
            (string) $companyPayload['uuid'],
            isset($companyPayload['code']) ? (string) $companyPayload['code'] : null,
            isset($companyPayload['name']) ? (string) $companyPayload['name'] : null,
        );
    }
}
