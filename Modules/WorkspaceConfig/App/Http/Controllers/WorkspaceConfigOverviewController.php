<?php

namespace Modules\WorkspaceConfig\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Evaluation\App\Services\EvaluationCriteriaService;
use Modules\Identity\App\Hrm\Exceptions\HrmDatabaseUnavailable;
use Modules\Identity\App\Hrm\Services\HrmDepartmentSyncService;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;
use Modules\Identity\App\Repositories\Contracts\DepartmentRepositoryInterface;
use Modules\Identity\App\Services\ActivityLogService;
use Modules\WorkspaceConfig\App\Exceptions\MemberDepartmentNotAssignable;
use Modules\WorkspaceConfig\App\Http\Requests\AssignWorkspaceConfigMemberDepartmentRequest;
use Modules\WorkspaceConfig\App\Services\DepartmentSidebarConfigService;
use Modules\WorkspaceConfig\App\Services\WorkspaceConfigMemberService;

/**
 * superadmin/workspace-config — xem TỔNG HỢP workspace của mọi phòng ban:
 * 1 bảng liệt kê + bấm vào 1 dòng xem chi tiết. Phần lớn chỉ xem (sửa
 * sidebar chỉ làm được ở WorkspaceConfigSidebarController, scope đúng
 * phòng ban của user đó) — 2 NGOẠI LỆ super_admin được làm thay
 * department_director: gán department_id cho tài khoản (assignDepartment,
 * bước chặn trước khi department_director có thể làm bất cứ gì — chưa có
 * phòng ban thì không thấy nút gán vai trò), và gán vai trò phòng ban cho
 * thành viên bất kỳ phòng ban nào (xem
 * WorkspaceConfigMemberController::assignRoleForDepartment()).
 */
class WorkspaceConfigOverviewController extends Controller
{
    public function __construct(
        private readonly DepartmentRepositoryInterface $departments,
        private readonly WorkspaceConfigMemberService $members,
        private readonly DepartmentSidebarConfigService $sidebarConfigs,
        private readonly EvaluationCriteriaService $evaluationCriteria,
        private readonly ActivityLogService $activityLogs,
        private readonly HrmDepartmentSyncService $hrmDepartmentSync,
    ) {}

    public function index(): JsonResponse
    {
        $this->hrmDepartmentSync->syncDepartmentsFromHrm();
        $this->hrmDepartmentSync->syncDepartmentManagersFromHrm();

        $departments = HrmDepartmentSyncService::isConfigured()
            ? $this->departments->allSyncedFromHrm()
            : $this->departments->all();

        return response()->json([
            'departments' => $this->members->overviewRows($departments),
        ]);
    }

    public function showDepartment(int $department): JsonResponse
    {
        $model = $this->departments->find($department);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy phòng ban.'], 404);
        }

        return response()->json([
            'department' => [
                'id' => $model->id,
                'code' => $model->code,
                'name' => $model->name,
                'is_active' => (bool) $model->is_active,
                'director' => $this->members->directorForDepartment($model->id),
            ],
            'members' => $this->members->forDepartment($model->id),
            'sidebar_menus' => $this->sidebarConfigs->forDepartment($model->id),
            'sidebar_sections' => $this->sidebarConfigs->sectionsForDepartment($model->id),
            'evaluation_criteria' => $this->evaluationCriteria->listForDepartment($model->id),
            'assignable_roles' => $this->members->assignableRoles(),
        ]);
    }

    /** Tài khoản chưa gắn phòng ban nào — chờ super_admin gán tay. */
    public function unassignedMembers(): JsonResponse
    {
        $this->hrmDepartmentSync->syncDepartmentsFromHrm();

        $departmentList = HrmDepartmentSyncService::isConfigured()
            ? $this->departments->allSyncedFromHrm()
            : $this->departments->all();

        return response()->json([
            'members' => $this->members->unassignedMembers(),
            'departments' => $departmentList->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
            ])->values(),
        ]);
    }

    /** Toàn bộ nhân sự: MySQL VA-HRM khi đã cấu hình, không gọi API. */
    public function membersByDepartment(): JsonResponse
    {
        try {
            $payload = HrmEmployeeDirectory::isConfigured()
                ? $this->members->rosterFromHrmDatabase()
                : $this->members->workspaceRoster();
        } catch (HrmDatabaseUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json($payload);
    }

    /** Gán phòng ban cho nhân sự HRM — tạo tài khoản workspace nếu chưa có. */
    public function assignHrmDepartment(AssignWorkspaceConfigMemberDepartmentRequest $request, string $employee): JsonResponse
    {
        $data = $request->validated();

        try {
            $presented = $this->members->assignDepartmentFromHrm($employee, (int) $data['department_id']);
        } catch (MemberDepartmentNotAssignable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (HrmDatabaseUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $this->activityLogs->record(
            'member.department.assign',
            'Gán phòng ban '.($presented['department']['name'] ?? '').' cho '.($presented['name'] ?? ''),
            $request->user(),
            'user',
            $presented['id'] ?? null,
            ['department_id' => $data['department_id'], 'hrm_employee_uuid' => $employee],
        );

        return response()->json(['member' => $presented]);
    }

    /** Gán/đổi phòng ban cho 1 tài khoản — bước chặn trước khi vào cấu hình phòng ban. */
    public function assignDepartment(AssignWorkspaceConfigMemberDepartmentRequest $request, int $user): JsonResponse
    {
        $data = $request->validated();

        try {
            $member = $this->members->assignDepartment($user, (int) $data['department_id']);
        } catch (MemberDepartmentNotAssignable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $presented = $this->members->presentMember($member);

        $this->activityLogs->record(
            'member.department.assign',
            'Gán phòng ban '.($presented['department']['name'] ?? '').' cho '.$member->name,
            $request->user(),
            'user',
            $member->id,
            ['department_id' => $data['department_id']],
        );

        return response()->json(['member' => $presented]);
    }
}
