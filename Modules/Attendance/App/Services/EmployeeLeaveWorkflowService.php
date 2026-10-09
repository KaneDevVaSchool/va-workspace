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
 * HR theo dõi = nhân sự liên hệ phòng Nhân sự (catalog HRM /employees).
 */
class EmployeeLeaveWorkflowService
{
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

        $directManager = $this->resolveDirectManager($user, (string) $employeeUuid);
        $departmentHead = $this->resolveDepartmentHead((string) $employeeUuid);

        $approver = $directManager ?? $departmentHead;
        $notifyTo = $this->resolveNotifyTo($directManager, $departmentHead, $approver);

        $groupLabel = $this->approverGroupLabel((string) $employeeUuid, $user);

        return [
            'approver_group_label' => $groupLabel,
            'approver' => $approver !== null ? $this->presentApproverCard($approver) : null,
            'notify_to' => $notifyTo,
            'watcher_label' => 'Người theo dõi (HR)',
            'hr_watchers' => $this->listHrWatchers(),
            'message' => $approver === null ? 'Chưa xác định được người duyệt từ HRM.' : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveDirectManager(User $user, string $employeeUuid): ?array
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
     * @return list<array{uuid: string, full_name: string, job_title: ?string}>
     */
    private function listHrWatchers(): array
    {
        if (! HrmEmployeeDirectory::isConfigured()) {
            return [];
        }

        try {
            return $this->directory->listHrContactEmployees();
        } catch (HrmDatabaseUnavailable $e) {
            Log::warning('attendance.leave_workflow.hr_contacts_failed', ['message' => $e->getMessage()]);

            return [];
        }
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

        $jobTitle = null;
        $departmentName = null;

        if ($uuid !== '') {
            try {
                $employee = $this->hrmApi->getEmployee($uuid);
                if ($employee !== null) {
                    $jobTitle = $employee->primaryAssignment?->jobTitleName;
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
            'title' => $titleParts !== [] ? implode(' · ', $titleParts) : '',
            'source' => (string) ($person['source'] ?? ''),
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
            'notify_to' => null,
            'watcher_label' => 'Người theo dõi (HR)',
            'hr_watchers' => [],
            'message' => $message,
        ];
    }
}
