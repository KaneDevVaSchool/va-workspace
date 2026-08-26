<?php

namespace Modules\WorkspaceConfig\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\App\Services\ActivityLogService;
use Modules\WorkspaceConfig\App\Http\Requests\ReorderGlobalMenuLayoutRequest;
use Modules\WorkspaceConfig\App\Http\Requests\UpdateGlobalMenuSectionRequest;
use Modules\WorkspaceConfig\App\Http\Requests\UpdateGlobalMenuVisibilityRequest;
use Modules\WorkspaceConfig\App\Services\GlobalMenuVisibilityService;

/**
 * superadmin/workspace-config/global-menu — ẩn/hiện, đổi tên và sắp xếp
 * menu sidebar Ở MỨC TOÀN HỆ THỐNG, áp dụng cho mọi tài khoản không phải
 * super_admin. Chỉ super_admin (permission workspace_config.manage_global_menu)
 * truy cập.
 */
class WorkspaceConfigGlobalMenuController extends Controller
{
    public function __construct(
        private readonly GlobalMenuVisibilityService $service,
        private readonly ActivityLogService $activityLogs,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->service->forListingFull());
    }

    /**
     * Cập nhật 1 item: toggle ẩn/hiện và/hoặc đổi tên tuỳ chỉnh.
     * Trường nào có mặt trong request thì xử lý trường đó (giống pattern
     * WorkspaceConfigSidebarController::update).
     */
    public function update(UpdateGlobalMenuVisibilityRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            if (array_key_exists('is_hidden', $data)) {
                $row = $this->service->setHidden($data['menu_key'], (bool) $data['is_hidden'], $request->user()?->id);

                $this->activityLogs->record(
                    'workspace_config.global_menu.update',
                    ($data['is_hidden'] ? 'Ẩn' : 'Hiện').' menu "'.$this->service->label($data['menu_key']).'" cho toàn hệ thống',
                    $request->user(),
                    'global_menu_visibility',
                    $row->id,
                );
            }

            if (array_key_exists('custom_label', $data)) {
                $row = $this->service->setCustomLabel($data['menu_key'], $data['custom_label'], $request->user()?->id);

                $label = $data['custom_label'] ? '"'.$data['custom_label'].'"' : 'mặc định';
                $this->activityLogs->record(
                    'workspace_config.global_menu.rename',
                    'Đổi tên menu "'.$this->service->label($data['menu_key']).'" thành '.$label.' cho toàn hệ thống',
                    $request->user(),
                    'global_menu_visibility',
                    $row->id,
                );
            }
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->service->forListingFull());
    }

    /**
     * Đổi tên 1 section menu (nhóm Điều hướng, Quản trị, …).
     */
    public function updateSection(UpdateGlobalMenuSectionRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $this->service->updateSectionLabel(
                $data['section_key'],
                $data['custom_label'] ?? null,
                $request->user()?->id,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $labelText = ($data['custom_label'] ?? null) ? '"'.$data['custom_label'].'"' : 'mặc định';
        $this->activityLogs->record(
            'workspace_config.global_menu.rename_section',
            'Đổi tên nhóm "'.$data['section_key'].'" thành '.$labelText.' cho toàn hệ thống',
            $request->user(),
        );

        return response()->json($this->service->forListingFull());
    }

    /**
     * Lưu thứ tự + nhóm mới sau khi kéo thả trên trang quản lý.
     */
    public function reorderLayout(ReorderGlobalMenuLayoutRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $this->service->reorderItems($data['items'], $request->user()?->id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->activityLogs->record(
            'workspace_config.global_menu.reorder',
            'Sắp xếp lại thứ tự menu cho toàn hệ thống',
            $request->user(),
        );

        return response()->json($this->service->forListingFull());
    }
}
