<?php

namespace Modules\WorkspaceConfig\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Evaluation\App\Services\EvaluationCriteriaService;
use Modules\Identity\App\Repositories\Contracts\DepartmentRepositoryInterface;
use Modules\WorkspaceConfig\App\Services\DepartmentSidebarConfigService;
use Modules\WorkspaceConfig\App\Services\WorkspaceConfigMemberService;

/**
 * superadmin/workspace-config — xem TỔNG HỢP workspace của mọi phòng ban:
 * 1 bảng liệt kê + bấm vào 1 dòng xem chi tiết. Chỉ xem, super_admin
 * không sửa thay department_director (sửa sidebar chỉ làm được ở
 * WorkspaceConfigSidebarController, scope đúng phòng ban của user đó).
 */
class WorkspaceConfigOverviewController extends Controller
{
    public function __construct(
        private readonly DepartmentRepositoryInterface $departments,
        private readonly WorkspaceConfigMemberService $members,
        private readonly DepartmentSidebarConfigService $sidebarConfigs,
        private readonly EvaluationCriteriaService $evaluationCriteria,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'departments' => $this->members->overviewRows($this->departments->all()),
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
            'evaluation_criteria' => $this->evaluationCriteria->listForDepartment($model->id),
        ]);
    }
}
