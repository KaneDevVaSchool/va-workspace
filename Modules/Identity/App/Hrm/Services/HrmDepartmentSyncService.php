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
     * Phòng ban HRM của tài khoản (org unit type department). Không đọc và
     * không ghi department Workspace.
     *
     * @return array{uuid: string, code: mixed, name: mixed}|null
     */
    public function hrmDepartmentFor(User $user): ?array
    {
        if (! HrmEmployeeDirectory::isConfigured()) {
            return null;
        }

        try {
            $orgUnit = $this->directory->primaryDepartmentOrgUnit($user->hrm_employee_uuid, $user->email);
        } catch (HrmDatabaseUnavailable $e) {
            Log::warning('hrm.user_department.lookup_failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if ($orgUnit === null || ! filled($orgUnit['uuid'] ?? null)) {
            return null;
        }

        return [
            'uuid' => (string) $orgUnit['uuid'],
            'code' => $orgUnit['code'] ?? null,
            'name' => $orgUnit['name'] ?? null,
        ];
    }

    /**
     * Phòng ban Workspace đã đồng bộ cùng uuid HRM, chỉ để đọc tiêu chí.
     * Không tạo dòng mới và không gán users.department_id.
     */
    public function existingDepartmentIdFor(User $user): ?int
    {
        $hrm = $this->hrmDepartmentFor($user);
        if ($hrm === null) {
            return null;
        }

        $id = Department::query()->where('hrm_org_unit_uuid', $hrm['uuid'])->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Id phòng ban Workspace trùng uuid phòng ban HRM của user. Tạo dòng
     * danh mục nếu HRM đã có phòng ban mà Workspace chưa đồng bộ. Không ghi
     * users.department_id.
     */
    public function departmentIdMatchingHrm(User $user): ?int
    {
        $existing = $this->existingDepartmentIdFor($user);
        if ($existing !== null) {
            return $existing;
        }

        if (! self::isConfigured()) {
            return null;
        }

        try {
            $orgUnit = $this->directory->primaryDepartmentOrgUnit($user->hrm_employee_uuid, $user->email);
        } catch (HrmDatabaseUnavailable $e) {
            Log::warning('hrm.user_department.lookup_failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if ($orgUnit === null || ! filled($orgUnit['uuid'] ?? null)) {
            return null;
        }

        $this->upsertDepartment($orgUnit);

        $id = Department::query()->where('hrm_org_unit_uuid', (string) $orgUnit['uuid'])->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Không gán department Workspace. Phòng ban của tài khoản đọc từ HRM.
     */
    public function ensureUserDepartment(User $user): void
    {
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
        $presentUuids = [];

        DB::transaction(function () use ($orgUnits, &$presentUuids): void {
            foreach ($orgUnits as $orgUnit) {
                if (! is_array($orgUnit) || ! isset($orgUnit['uuid'])) {
                    continue;
                }

                $type = (string) ($orgUnit['type'] ?? '');
                if ($type !== '' && ! in_array($type, self::ASSIGNABLE_ORG_UNIT_TYPES, true)) {
                    continue;
                }

                $presentUuids[] = (string) $orgUnit['uuid'];
                $this->upsertDepartment($orgUnit);
            }
        });

        $this->deactivateStaleHrmDepartments($presentUuids);
    }

    /**
     * Phòng ban workspace gắn uuid HRM nhưng org unit không còn trên HRM
     * (soft delete / đổi uuid) — ẩn khỏi dropdown, tránh trùng tên/mã cũ.
     *
     * @param  list<string>  $presentUuids
     */
    private function deactivateStaleHrmDepartments(array $presentUuids): void
    {
        $query = Department::query()
            ->whereNotNull('hrm_org_unit_uuid')
            ->where('is_active', true);

        if ($presentUuids !== []) {
            $query->whereNotIn('hrm_org_unit_uuid', $presentUuids);
        }

        $query->update(['is_active' => false]);
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
        $isActive = ($orgUnit['status'] ?? 'active') === 'active';
        $company = $this->resolveCompany($orgUnit['company'] ?? null);
        $code = $this->uniqueDepartmentCode($uuid, $orgUnit, $company);

        $department = Department::query()->where('hrm_org_unit_uuid', $uuid)->first();

        $attributes = [
            'code' => $code,
            'name' => $name !== '' ? $name : $code,
            'external_code' => $this->hrmExternalCode($orgUnit),
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

    /**
     * Mã hiển thị/tích hợp — ưu tiên «Mã phần mềm» danh mục HRM
     * (`/organization/departments`), fallback mã org unit.
     *
     * @param  array<string, mixed>  $orgUnit
     */
    private function hrmExternalCode(array $orgUnit): ?string
    {
        $software = trim((string) ($orgUnit['software_code'] ?? ''));
        if ($software !== '') {
            return $software;
        }

        $code = trim((string) ($orgUnit['code'] ?? ''));

        return $code !== '' ? $code : null;
    }

    /** @param array<string, mixed> $orgUnit */
    private function uniqueDepartmentCode(string $uuid, array $orgUnit, ?Company $company): string
    {
        $candidate = $this->hrmExternalCode($orgUnit) ?? '';
        if ($candidate === '') {
            $candidate = 'HRM-'.strtoupper(substr(str_replace('-', '', $uuid), 0, 12));
        }

        if ($company !== null && filled($company->code)) {
            $scoped = strtoupper((string) $company->code).'_'.$candidate;
            if (! $this->departmentCodeTakenByOtherUuid($scoped, $uuid)) {
                return $scoped;
            }
        }

        if (! $this->departmentCodeTakenByOtherUuid($candidate, $uuid)) {
            return $candidate;
        }

        return $candidate.'-'.substr($uuid, 0, 8);
    }

    private function departmentCodeTakenByOtherUuid(string $code, string $uuid): bool
    {
        return Department::query()
            ->where('code', $code)
            ->where(function ($query) use ($uuid): void {
                $query->whereNull('hrm_org_unit_uuid')
                    ->orWhere('hrm_org_unit_uuid', '!=', $uuid);
            })
            ->exists();
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
