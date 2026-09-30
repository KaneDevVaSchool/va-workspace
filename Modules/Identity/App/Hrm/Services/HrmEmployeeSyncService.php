<?php

namespace Modules\Identity\App\Hrm\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Identity\App\Hrm\DTO\HrmAssignmentData;
use Modules\Identity\App\Hrm\DTO\HrmEmployeeData;
use Modules\Identity\App\Models\Company;

/**
 * Áp dữ liệu nhân sự từ VA-HRM lên User — dùng chung cho SSO lần-đầu-login
 * (HrmSsoService) và các Job xử lý webhook. CHỈ cập nhật field hiển thị:
 * KHÔNG đụng department_id, roles, status (trừ đường riêng qua
 * ProcessEmployeeTerminatedJob) — theo quyết định "chỉ sync hiển thị,
 * không tự đổi quyền/phòng ban".
 */
class HrmEmployeeSyncService
{
    public function applyToUser(User $user, HrmEmployeeData $employee): User
    {
        DB::transaction(function () use ($user, $employee): void {
            $primary = $employee->primaryAssignment;

            $user->fill([
                'employee_code' => $employee->code,
                'job_title_name' => $primary?->jobTitleName,
                'job_position_level' => $primary?->jobPositionLevel,
                'company_id' => $primary?->companyHrmUuid !== null
                    ? $this->resolveCompany($primary)->id
                    : $user->company_id,
                'manager_employee_uuid' => $employee->managerEmployeeUuid,
                'manager_display_name' => $employee->managerDisplayName,
                'hrm_synced_at' => now(),
            ])->save();

            $this->syncConcurrentPositions($user, $employee->concurrentAssignments);
        });

        return $user->fresh(['company', 'concurrentPositions']);
    }

    /** Find-or-create theo hrm_uuid — Company không ảnh hưởng permission, an toàn để auto-create. */
    private function resolveCompany(HrmAssignmentData $assignment): Company
    {
        return Company::query()->updateOrCreate(
            ['hrm_uuid' => $assignment->companyHrmUuid],
            [
                'code' => $assignment->companyCode ?? $assignment->companyHrmUuid,
                'name' => $assignment->companyName ?? $assignment->companyCode ?? $assignment->companyHrmUuid,
            ],
        );
    }

    /**
     * @param list<HrmAssignmentData> $concurrentAssignments
     *
     * AssignmentResource của HRM không có uuid ổn định riêng cho từng dòng
     * kiêm nhiệm — chiến lược sync là xoá hết + insert lại toàn bộ.
     */
    private function syncConcurrentPositions(User $user, array $concurrentAssignments): void
    {
        $user->concurrentPositions()->delete();

        foreach ($concurrentAssignments as $assignment) {
            $user->concurrentPositions()->create([
                'job_title_name' => $assignment->jobTitleName ?? '(chưa rõ chức vụ)',
                'company_name' => $assignment->companyName,
                'org_unit_name' => $assignment->orgUnitName,
                'org_unit_path' => $assignment->orgUnitPath,
                'effective_from' => $assignment->effectiveFrom,
                'effective_to' => $assignment->effectiveTo,
            ]);
        }
    }
}
