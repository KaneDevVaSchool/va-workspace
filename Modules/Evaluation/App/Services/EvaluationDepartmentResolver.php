<?php

namespace Modules\Evaluation\App\Services;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\App\Hrm\Services\HrmDepartmentSyncService;
use Modules\Identity\App\Hrm\Services\HrmWorkspaceConstraint;
use Modules\Identity\App\Services\PermissionService;

/**
 * Phòng ban cho module Đánh giá — khớp /api/me (HRM) và bản ghi tiêu chí workspace.
 */
class EvaluationDepartmentResolver
{
    public function __construct(
        private readonly HrmDepartmentSyncService $hrmDepartments,
        private readonly PermissionService $permissions,
        private readonly HrmWorkspaceConstraint $hrmConstraint,
    ) {}

    public function workspaceDepartmentIdFor(User $user): ?int
    {
        if (! HrmDepartmentSyncService::isConfigured()) {
            return $user->department_id !== null ? (int) $user->department_id : null;
        }

        return $this->hrmDepartments->departmentIdMatchingHrm($user);
    }

    public function idOrFail(Request $request): int|JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (HrmDepartmentSyncService::isConfigured() && ! filled($user->hrm_employee_uuid) && ! filled($user->email)) {
            return response()->json(['message' => 'Tài khoản chưa liên kết nhân sự VA-HRM.'], 422);
        }

        $departmentId = $this->workspaceDepartmentIdFor($user);
        if ($departmentId === null) {
            $message = HrmDepartmentSyncService::isConfigured()
                ? 'Không xác định được phòng ban từ HRM hoặc phòng ban chưa đồng bộ workspace.'
                : 'Tài khoản chưa gắn phòng ban.';

            return response()->json(['message' => $message], 422);
        }

        return $departmentId;
    }

    /** GET tiêu chí / loại tiêu chí — superadmin có thể ?department_id= */
    public function resolveForRead(Request $request): int|JsonResponse
    {
        $queryDeptId = $request->query('department_id');

        if ($queryDeptId !== null && $queryDeptId !== '') {
            if (! $this->permissions->allows($request->user(), 'workspace_config.view_all')) {
                return response()->json(['message' => 'Không có quyền xem tiêu chí phòng ban khác.'], 403);
            }

            $id = (int) $queryDeptId;
            $invalid = $this->hrmConstraint->validateDepartmentIds([$id]);
            if ($invalid !== null) {
                return response()->json(['message' => $invalid], 422);
            }

            return $id;
        }

        return $this->idOrFail($request);
    }

    /** GET loại tiêu chí — cùng quy tắc đọc, message 403 riêng. */
    public function resolveCriterionTypesForRead(Request $request): int|JsonResponse
    {
        $queryDeptId = $request->query('department_id');

        if ($queryDeptId !== null && $queryDeptId !== '') {
            if (! $this->permissions->allows($request->user(), 'workspace_config.view_all')) {
                return response()->json(['message' => 'Không có quyền xem loại tiêu chí phòng ban khác.'], 403);
            }

            $id = (int) $queryDeptId;
            $invalid = $this->hrmConstraint->validateDepartmentIds([$id]);
            if ($invalid !== null) {
                return response()->json(['message' => $invalid], 422);
            }

            return $id;
        }

        return $this->idOrFail($request);
    }

    /** department_id bắt buộc (vd. quality-levels cho chấm việc Project). */
    public function assertHrmSyncedDepartment(int $departmentId): int|JsonResponse
    {
        if ($departmentId <= 0) {
            return response()->json(['message' => 'Thiếu department_id.'], 422);
        }

        $invalid = $this->hrmConstraint->validateDepartmentIds([$departmentId]);
        if ($invalid !== null) {
            return response()->json(['message' => $invalid], 422);
        }

        return $departmentId;
    }
}
