<?php

namespace Modules\WorkspaceConfig\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\App\Services\ActivityLogService;
use Modules\WorkspaceConfig\App\Http\Requests\UpdateSidebarVisibilityRequest;
use Modules\WorkspaceConfig\App\Services\DepartmentSidebarConfigService;

/**
 * manager/workspace-config/sidebar — bật/tắt menu sidebar của CHÍNH phòng
 * ban user đang đăng nhập. department_id luôn lấy từ
 * $request->user()->department_id (xem WorkspaceConfigMemberController
 * cho lý do không nhận từ query/body).
 */
class WorkspaceConfigSidebarController extends Controller
{
    public function __construct(
        private readonly DepartmentSidebarConfigService $service,
        private readonly ActivityLogService $activityLogs,
    ) {}

    private function departmentIdOrFail(Request $request): int|JsonResponse
    {
        $departmentId = $request->user()?->department_id;

        return $departmentId ? (int) $departmentId : response()->json(['message' => 'Tài khoản chưa gắn với phòng ban nào.'], 422);
    }

    public function index(Request $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        return response()->json([
            'menus' => $this->service->forDepartment($departmentId),
        ]);
    }

    public function update(UpdateSidebarVisibilityRequest $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        $data = $request->validated();

        try {
            $config = $this->service->setVisibility(
                $departmentId,
                $data['menu_key'],
                $data['is_visible'],
                $request->user()?->id,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->activityLogs->record(
            'workspace_config.sidebar.update',
            ($data['is_visible'] ? 'Bật' : 'Tắt').' menu "'.$this->service->menuLabel($data['menu_key']).'" cho phòng ban',
            $request->user(),
            'department_sidebar_config',
            $config->id,
        );

        return response()->json([
            'menu' => [
                'menu_key' => $config->menu_key,
                'is_visible' => $config->is_visible,
            ],
        ]);
    }
}
