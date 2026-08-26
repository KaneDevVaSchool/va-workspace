<?php

namespace Modules\WorkspaceConfig\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\App\Services\ActivityLogService;
use Modules\WorkspaceConfig\App\Http\Requests\UpdateGlobalMenuVisibilityRequest;
use Modules\WorkspaceConfig\App\Services\GlobalMenuVisibilityService;

/**
 * superadmin/workspace-config/global-menu — ẩn/hiện menu sidebar Ở MỨC
 * TOÀN HỆ THỐNG, áp dụng cho mọi tài khoản không phải super_admin. Chỉ
 * super_admin (permission workspace_config.manage_global_menu) truy cập.
 */
class WorkspaceConfigGlobalMenuController extends Controller
{
    public function __construct(
        private readonly GlobalMenuVisibilityService $service,
        private readonly ActivityLogService $activityLogs,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['menus' => $this->service->forListing()]);
    }

    public function update(UpdateGlobalMenuVisibilityRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $row = $this->service->setHidden($data['menu_key'], (bool) $data['is_hidden'], $request->user()?->id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->activityLogs->record(
            'workspace_config.global_menu.update',
            ($data['is_hidden'] ? 'Ẩn' : 'Hiện').' menu "'.$this->service->label($data['menu_key']).'" cho toàn hệ thống',
            $request->user(),
            'global_menu_visibility',
            $row->id,
        );

        return response()->json(['menus' => $this->service->forListing()]);
    }
}
