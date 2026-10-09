<?php

namespace Modules\Attendance\App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;
use Modules\Identity\App\Hrm\Exceptions\HrmDatabaseUnavailable;
use Modules\Identity\App\Hrm\Services\HrmApiClient;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;

/**
 * Luồng duyệt đơn nghỉ — quản lý trực tiếp (HRM), fallback trưởng phòng ban;
 * Nhân sự phụ trách = HR gắn trên hồ sơ nhân viên (portal HRM «Nhân sự phụ trách»).
 */
class EmployeeLeaveWorkflowService
{
    private const HR_IN_CHARGE_CAPTION = 'Nhân viên Hành chính Nhân sự phụ trách hồ sơ này.';
    public function __construct(
        private readonly HrmApiClient $hrmApi,
        private readonly HrmEmployeeDirectory $directory,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $employeeUuid = $user->hrm_employee_uuid;
        if (! filled($employeeUuid)) {
            return $this->emptyPayload('Tài khoản chưa liên kết nhân sự HRM.');
        }

        $directFromProfile = $this->resolveDirectManagerFromProfile((string) $employeeUuid);
        $directManager = $directFromProfile ?? $this->resolveDirectManagerFromOrgTree($user, (string) $employeeUuid);
        $departmentHead = $this->resolveDepartmentHead((string) $employeeUuid);

        $approver = $directManager ?? $departmentHead;
        $notifyTo = $this->resolveNotifyTo($directManager, $departmentHead, $approver);

        $groupLabel = $this->approverGroupLabel((string) $employeeUuid, $user);

        return [
            'approver_group_label' => $groupLabel,
            'approver' => $approver !== null ? $this->presentApproverCard($approver) : null,
            'direct_manager' => $this->presentContactPerson($directFromProfile),
            'direct_manager_label' => 'Cấp trên trực tiếp',
            'notify_to' => $notifyTo,
            'watcher_label' => 'Nhân sự phụ trách',
            'hr_responsible' => $this->resolveHrPersonInCharge((string) $employeeUuid),
            'hr_responsible_caption' => self::HR_IN_CHARGE_CAPTION,
            'message' => $approver === null ? 'Chưa xác định được người duyệt từ HRM.' : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveDirectManagerFromProfile(string $employeeUuid): ?array
    {
        try {
            $payload = $this->hrmApi->getEmployeePayload($employeeUuid);
            $label = trim((string) ($payload['direct_manager_name'] ?? ''));
            if ($label !== '') {
                $fromLabel = $this->personFromManagerLabel($label);
                if ($fromLabel !== null) {
                    return $this->enrichPerson($fromLabel, 'direct_manager');
                }
            }
        } catch (HrmApiUnavailable $e) {
            Log::warning('attendance.leave_workflow.direct_manager_profile_api_failed', [
                'employee_uuid' => $employeeUuid,
                'message' => $e->getMessage(),
            ]);
        }

        if (HrmEmployeeDirectory::isConfigured()) {
            try {
                $fromDb = $this->directory->resolveDirectManagerFromProfile($employeeUuid);
                if ($fromDb !== null) {
                    return $this->enrichPerson($fromDb, 'direct_manager');
                }
            } catch (HrmDatabaseUnavailable $e) {
                Log::warning('attendance.leave_workflow.direct_manager_profile_db_failed', [
                    'employee_uuid' => $employeeUuid,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveDirectManagerFromOrgTree(User $user, string $employeeUuid): ?array
    {
        try {
            $manager = $this->hrmApi->getEmployeeManager($employeeUuid);
            if ($this->isUsableManager($manager, $employeeUuid)) {
                return $this->enrichPerson($manager, 'direct_manager');
            }
        } catch (HrmApiUnavailable $e) {
            Log::warning('attendance.leave_workflow.manager_api_failed', [
                'employee_uuid' => $employeeUuid,
                'message' => $e->getMessage(),
            ]);
        }

        if (filled($user->manager_employee_uuid) && $user->manager_employee_uuid !== $employeeUuid) {
            return $this->enrichPerson([
                'uuid' => (string) $user->manager_employee_uuid,
                'full_name' => $user->manager_display_name ?? $user->manager_employee_uuid,
            ], 'direct_manager');
        }

        return null;
    }

    /**
     * @return array{uuid: ?string, full_name: string, code: ?string, job_title: ?string}|null
     */
    private function personFromManagerLabel(string $label): ?array
    {
        $label = trim($label);
        if ($label === '') {
            return null;
        }

        $code = $this->extractEmployeeCodeFromLabel($label);
        if ($code !== null) {
            try {
                $match = $this->hrmApi->findEmployeeByCode($code);
                if (is_array($match) && filled($match['uuid'] ?? null)) {
                    return [
                        'uuid' => (string) $match['uuid'],
                        'full_name' => trim((string) ($match['full_name'] ?? '')),
                        'code' => (string) ($match['code'] ?? $code),
                        'job_title' => filled($match['job_title_name'] ?? null)
                            ? (string) $match['job_title_name']
                            : null,
                    ];
                }
            } catch (HrmApiUnavailable) {
                // fallback tên từ snapshot
            }
        }

        $name = trim((string) preg_replace('/\s*\([^)]*\)\s*$/u', '', $label));

        return $name !== '' ? [
            'uuid' => null,
            'full_name' => $name,
            'code' => $code,
            'job_title' => null,
        ] : null;
    }

    private function extractEmployeeCodeFromLabel(string $label): ?string
    {
        if (preg_match('/\(([A-Za-z]{2}\d+)\)\s*$/u', trim($label), $matches)) {
            return strtoupper($matches[1]);
        }

        if (preg_match('/\(([A-Za-z0-9_-]+)\)\s*$/u', trim($label), $matches)) {
            return strtoupper(trim($matches[1]));
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveDepartmentHead(string $employeeUuid): ?array
    {
        if (HrmEmployeeDirectory::isConfigured()) {
            try {
                $head = $this->directory->resolveDepartmentHeadForEmployee($employeeUuid);
                if ($head !== null && ($head['uuid'] ?? '') !== $employeeUuid) {
                    return $this->enrichPerson($head, 'department_head');
                }
            } catch (HrmDatabaseUnavailable $e) {
                Log::warning('attendance.leave_workflow.dept_head_db_failed', [
                    'employee_uuid' => $employeeUuid,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $this->resolveDepartmentHeadViaApi($employeeUuid);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveDepartmentHeadViaApi(string $employeeUuid): ?array
    {
        try {
            $employee = $this->hrmApi->getEmployee($employeeUuid);
        } catch (HrmApiUnavailable $e) {
            Log::warning('attendance.leave_workflow.employee_api_failed', [
                'employee_uuid' => $employeeUuid,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if ($employee === null || $employee->primaryAssignment?->orgUnitHrmUuid === null) {
            return null;
        }

        $orgUnitUuid = (string) $employee->primaryAssignment->orgUnitHrmUuid;

        for ($guard = 0; $guard < 12; $guard++) {
            try {
                $orgUnit = $this->hrmApi->getOrgUnit($orgUnitUuid);
            } catch (HrmApiUnavailable) {
                break;
            }

            if ($orgUnit === null) {
                break;
            }

            if (($orgUnit['type'] ?? '') === 'department') {
                $manager = $orgUnit['manager'] ?? null;
                if ($this->isUsableManager(is_array($manager) ? $manager : null, $employeeUuid)) {
                    $person = $this->enrichPerson($manager, 'department_head');
                    $person['department_name'] = (string) ($orgUnit['name'] ?? '');

                    return $person;
                }
            }

            $parentUuid = $orgUnit['parent_uuid']
                ?? (is_array($orgUnit['parent'] ?? null) ? ($orgUnit['parent']['uuid'] ?? null) : null);

            if (! filled($parentUuid)) {
                break;
            }

            $orgUnitUuid = (string) $parentUuid;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $directManager
     * @param  array<string, mixed>|null  $departmentHead
     * @param  array<string, mixed>|null  $approver
     */
    private function resolveNotifyTo(?array $directManager, ?array $departmentHead, ?array $approver): ?string
    {
        if ($approver === null) {
            return null;
        }

        $approverSource = (string) ($approver['source'] ?? '');

        if ($approverSource === 'department_head' && $directManager !== null) {
            return trim((string) ($directManager['full_name'] ?? '')) ?: null;
        }

        if ($approverSource === 'direct_manager' && $departmentHead !== null) {
            $headUuid = (string) ($departmentHead['uuid'] ?? '');
            $approverUuid = (string) ($approver['uuid'] ?? '');
            if ($headUuid !== '' && $headUuid !== $approverUuid) {
                return trim((string) ($departmentHead['full_name'] ?? '')) ?: null;
            }
        }

        return null;
    }

    private function approverGroupLabel(string $employeeUuid, User $user): string
    {
        if (HrmEmployeeDirectory::isConfigured()) {
            try {
                $orgUnit = $this->directory->primaryDepartmentOrgUnit($employeeUuid, $user->email);
                $name = trim((string) ($orgUnit['name'] ?? ''));
                if ($name !== '') {
                    return "Người duyệt nhóm {$name}";
                }
            } catch (HrmDatabaseUnavailable) {
                // fallback below
            }
        }

        try {
            $employee = $this->hrmApi->getEmployee($employeeUuid);
            $unitName = trim((string) ($employee?->primaryAssignment?->orgUnitName ?? ''));
            if ($unitName !== '') {
                return "Người duyệt nhóm {$unitName}";
            }
        } catch (HrmApiUnavailable) {
            // ignore
        }

        $dept = trim((string) ($user->department?->name ?? ''));

        return $dept !== '' ? "Người duyệt nhóm {$dept}" : 'Người duyệt';
    }

    /**
     * @return array{full_name: string, code: ?string, title: string}|null
     */
    private function resolveHrPersonInCharge(string $employeeUuid): ?array
    {
        $raw = $this->loadHrInChargeRawFromHrmApis($employeeUuid);

        if ($raw === null && HrmEmployeeDirectory::isConfigured()) {
            try {
                $raw = $this->directory->resolveHrPersonInChargeForEmployee($employeeUuid);
            } catch (HrmDatabaseUnavailable $e) {
                Log::warning('attendance.leave_workflow.hr_in_charge_db_failed', [
                    'employee_uuid' => $employeeUuid,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($raw === null || trim((string) ($raw['full_name'] ?? '')) === '') {
            return null;
        }

        return $this->presentHrResponsible($raw);
    }

    /**
     * @return array{uuid: ?string, full_name: string, code: ?string, job_title: ?string}|null
     */
    private function loadHrInChargeRawFromHrmApis(string $employeeUuid): ?array
    {
        $includeQuery = ['include' => 'hrOwner,hr_owner'];

        $sources = [
            static fn () => $this->hrmApi->getEmployeePayload($employeeUuid, $includeQuery),
            static fn () => $this->hrmApi->getEmployeePayload($employeeUuid),
            static fn () => $this->hrmApi->getEmployeePayloadFromLeaveCatalog($employeeUuid, $includeQuery),
            static fn () => $this->hrmApi->getEmployeePayloadFromLeaveCatalog($employeeUuid),
        ];

        foreach ($sources as $fetch) {
            try {
                $payload = $fetch();
                $raw = $this->extractHrInChargeFromApiPayload($payload);
                if ($raw !== null) {
                    return $raw;
                }
            } catch (HrmApiUnavailable $e) {
                Log::warning('attendance.leave_workflow.hr_in_charge_api_failed', [
                    'employee_uuid' => $employeeUuid,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array{uuid: ?string, full_name: string, code: ?string, job_title: ?string}|null
     */
    private function extractHrInChargeFromApiPayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        foreach ([
            'hr_owner',
            'hr_owner_employee',
            'hr_responsible_employee',
            'hr_responsible',
            'hr_officer',
            'assigned_hr_employee',
            'hr_in_charge',
            'hr_person_in_charge',
            'person_in_charge',
        ] as $key) {
            $node = $payload[$key] ?? null;
            if (! is_array($node)) {
                continue;
            }

            $fromNode = $this->hrContactFromResourceNode($node);
            if ($fromNode !== null) {
                return $fromNode;
            }
        }

        foreach ([
            'hr_owner_name',
            'hr_owner_full_name',
            'hr_responsible_name',
            'hr_officer_name',
            'hr_person_in_charge_name',
            'assigned_hr_name',
        ] as $nameKey) {
            $name = trim((string) ($payload[$nameKey] ?? ''));
            if ($name === '') {
                continue;
            }

            $codeKey = str_replace('_name', '_code', $nameKey);
            $code = filled($payload[$codeKey] ?? null) ? (string) $payload[$codeKey] : null;

            return [
                'uuid' => null,
                'full_name' => $name,
                'code' => $code,
                'job_title' => null,
            ];
        }

        $uuid = $payload['hr_owner_employee_uuid']
            ?? $payload['hr_responsible_employee_uuid']
            ?? $payload['assigned_hr_employee_uuid']
            ?? $payload['hr_officer_employee_uuid']
            ?? null;

        if (filled($uuid)) {
            return [
                'uuid' => (string) $uuid,
                'full_name' => trim((string) (
                    $payload['hr_owner_employee_name']
                    ?? $payload['hr_responsible_employee_name']
                    ?? $payload['assigned_hr_employee_name']
                    ?? $payload['hr_officer_name']
                    ?? ''
                )),
                'code' => filled($payload['hr_owner_employee_code'] ?? $payload['hr_responsible_employee_code'] ?? $payload['assigned_hr_employee_code'] ?? null)
                    ? (string) ($payload['hr_owner_employee_code'] ?? $payload['hr_responsible_employee_code'] ?? $payload['assigned_hr_employee_code'])
                    : null,
                'job_title' => null,
            ];
        }

        $internalId = $payload['hr_owner_employee_id']
            ?? $payload['hr_responsible_employee_id']
            ?? $payload['assigned_hr_employee_id']
            ?? $payload['hr_officer_employee_id']
            ?? null;

        if (filled($internalId)) {
            return $this->resolveHrContactByInternalEmployeeId((int) $internalId);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{uuid: ?string, full_name: string, code: ?string, job_title: ?string}|null
     */
    private function hrContactFromResourceNode(array $node): ?array
    {
        if (isset($node['data']) && is_array($node['data'])) {
            $nested = $this->hrContactFromResourceNode($node['data']);
            if ($nested !== null) {
                return $nested;
            }
        }

        $name = trim((string) ($node['full_name'] ?? $node['name'] ?? ''));
        $uuid = filled($node['uuid'] ?? null) ? (string) $node['uuid'] : null;
        $code = filled($node['code'] ?? null) ? (string) $node['code'] : null;
        $jobTitle = filled($node['job_title'] ?? $node['job_title_name'] ?? null)
            ? (string) ($node['job_title'] ?? $node['job_title_name'])
            : null;

        if ($name !== '') {
            return [
                'uuid' => $uuid,
                'full_name' => $name,
                'code' => $code,
                'job_title' => $jobTitle,
            ];
        }

        if ($uuid !== null) {
            return [
                'uuid' => $uuid,
                'full_name' => '',
                'code' => $code,
                'job_title' => $jobTitle,
            ];
        }

        $internalId = $node['id'] ?? $node['employee_id'] ?? null;
        if (filled($internalId)) {
            return $this->resolveHrContactByInternalEmployeeId((int) $internalId);
        }

        return null;
    }

    /**
     * @return array{uuid: ?string, full_name: string, code: ?string, job_title: ?string}|null
     */
    private function resolveHrContactByInternalEmployeeId(int $employeeId): ?array
    {
        if (! HrmEmployeeDirectory::isConfigured()) {
            return null;
        }

        try {
            return $this->directory->findEmployeeContactByInternalId($employeeId);
        } catch (HrmDatabaseUnavailable $e) {
            Log::warning('attendance.leave_workflow.hr_in_charge_id_lookup_failed', [
                'employee_id' => $employeeId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array{uuid: ?string, full_name: string, code: ?string, job_title: ?string}  $raw
     * @return array{full_name: string, code: ?string, title: string}
     */
    private function presentHrResponsible(array $raw): array
    {
        $fullName = trim((string) ($raw['full_name'] ?? ''));
        $code = filled($raw['code'] ?? null) ? (string) $raw['code'] : null;
        $jobTitle = filled($raw['job_title'] ?? null) ? (string) $raw['job_title'] : null;

        $uuid = filled($raw['uuid'] ?? null) ? (string) $raw['uuid'] : null;
        if ($uuid !== '') {
            try {
                $employee = $this->hrmApi->getEmployee($uuid);
                if ($employee !== null) {
                    if ($fullName === '') {
                        $fullName = $employee->fullName;
                    }
                    if ($code === null && filled($employee->code)) {
                        $code = $employee->code;
                    }
                    if ($jobTitle === null) {
                        $jobTitle = $employee->primaryAssignment?->jobTitleName;
                    }
                }
            } catch (HrmApiUnavailable) {
                // giữ giá trị từ payload / DB
            }
        }

        return [
            'full_name' => $fullName,
            'code' => $code,
            'title' => $jobTitle ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function isUsableManager(?array $payload, string $employeeUuid): bool
    {
        if ($payload === null || ! filled($payload['uuid'] ?? null)) {
            return false;
        }

        if ((string) $payload['uuid'] === $employeeUuid) {
            return false;
        }

        $status = (string) ($payload['status'] ?? 'active');

        return ! in_array($status, ['terminated', 'inactive'], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function enrichPerson(array $payload, string $source): array
    {
        $uuid = (string) ($payload['uuid'] ?? '');
        $fullName = trim((string) ($payload['full_name'] ?? ''));
        $code = filled($payload['code'] ?? null) ? (string) $payload['code'] : null;

        $jobTitle = filled($payload['job_title'] ?? null) ? (string) $payload['job_title'] : null;
        $departmentName = null;

        if ($uuid !== '') {
            try {
                $employee = $this->hrmApi->getEmployee($uuid);
                if ($employee !== null) {
                    if ($fullName === '') {
                        $fullName = $employee->fullName;
                    }
                    if ($code === null && filled($employee->code)) {
                        $code = $employee->code;
                    }
                    if ($jobTitle === null) {
                        $jobTitle = $employee->primaryAssignment?->jobTitleName;
                    }
                    $departmentName = $employee->primaryAssignment?->orgUnitName;
                }
            } catch (HrmApiUnavailable) {
                // giữ tên từ payload manager
            }
        }

        if ($departmentName === null && filled($payload['department_name'] ?? null)) {
            $departmentName = (string) $payload['department_name'];
        }

        return [
            'uuid' => $uuid,
            'full_name' => $fullName !== '' ? $fullName : $uuid,
            'code' => $code,
            'job_title' => $jobTitle,
            'department_name' => $departmentName,
            'source' => $source,
        ];
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array{name: string, title: string, source: string}
     */
    private function presentApproverCard(array $person): array
    {
        $titleParts = array_filter([
            filled($person['job_title'] ?? null) ? (string) $person['job_title'] : null,
            filled($person['department_name'] ?? null) ? (string) $person['department_name'] : null,
        ]);

        return [
            'name' => (string) ($person['full_name'] ?? ''),
            'code' => filled($person['code'] ?? null) ? (string) $person['code'] : null,
            'title' => $titleParts !== [] ? implode(' · ', $titleParts) : '',
            'source' => (string) ($person['source'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $person
     * @return array{full_name: string, code: ?string, title: string}|null
     */
    private function presentContactPerson(?array $person): ?array
    {
        if ($person === null) {
            return null;
        }

        $fullName = trim((string) ($person['full_name'] ?? ''));
        if ($fullName === '') {
            return null;
        }

        $titleParts = array_filter([
            filled($person['job_title'] ?? null) ? (string) $person['job_title'] : null,
            filled($person['department_name'] ?? null) ? (string) $person['department_name'] : null,
        ]);

        return [
            'full_name' => $fullName,
            'code' => filled($person['code'] ?? null) ? (string) $person['code'] : null,
            'title' => $titleParts !== [] ? implode(' · ', $titleParts) : '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(string $message): array
    {
        return [
            'approver_group_label' => 'Người duyệt',
            'approver' => null,
            'direct_manager' => null,
            'direct_manager_label' => 'Cấp trên trực tiếp',
            'notify_to' => null,
            'watcher_label' => 'Nhân sự phụ trách',
            'hr_responsible' => null,
            'hr_responsible_caption' => self::HR_IN_CHARGE_CAPTION,
            'message' => $message,
        ];
    }
}
