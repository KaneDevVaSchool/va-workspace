<?php

namespace Modules\Dashboard\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Dashboard\App\Http\Requests\ReorderDashboardQuickUtilitiesRequest;
use Modules\Dashboard\App\Http\Requests\StoreDashboardQuickUtilityRequest;
use Modules\Dashboard\App\Http\Requests\UpdateDashboardQuickUtilityRequest;
use Modules\Dashboard\App\Services\DashboardQuickUtilityService;
use Modules\Identity\App\Services\ActivityLogService;

class DashboardQuickUtilityController extends Controller
{
    public function __construct(
        private readonly DashboardQuickUtilityService $service,
        private readonly ActivityLogService $activityLogs,
    ) {}

    /** Tiện ích hiển thị trên /dashboard/me — mọi user có dashboard.view */
    public function indexForUser(): JsonResponse
    {
        return response()->json([
            'utilities' => $this->service->forDashboard(),
        ]);
    }

    /** Danh sách đầy đủ cho superadmin cấu hình */
    public function indexForAdmin(): JsonResponse
    {
        return response()->json([
            'utilities' => $this->service->forAdminListing(),
            'meta' => [
                'tones' => DashboardQuickUtilityService::TONES,
                'link_types' => DashboardQuickUtilityService::LINK_TYPES,
                'allow_create' => false,
                'catalog_size' => count(DashboardQuickUtilityService::CATALOG),
            ],
        ]);
    }

    public function store(StoreDashboardQuickUtilityRequest $request): JsonResponse
    {
        try {
            $row = $this->service->create($request->validated(), $request->user()?->id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->activityLogs->record(
            'dashboard.quick_utilities.create',
            'Thêm tiện ích nhanh "'.$row->label.'"',
            $request->user(),
            'dashboard_quick_utility',
            $row->id,
        );

        return response()->json([
            'utilities' => $this->service->forAdminListing(),
        ], 201);
    }

    public function update(UpdateDashboardQuickUtilityRequest $request, string $utilityKey): JsonResponse
    {
        try {
            $row = $this->service->update($utilityKey, $request->validated(), $request->user()?->id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->activityLogs->record(
            'dashboard.quick_utilities.update',
            'Cập nhật tiện ích nhanh "'.$row->label.'"',
            $request->user(),
            'dashboard_quick_utility',
            $row->id,
        );

        return response()->json([
            'utilities' => $this->service->forAdminListing(),
        ]);
    }

    public function destroy(Request $request, string $utilityKey): JsonResponse
    {
        try {
            $this->service->delete($utilityKey);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->activityLogs->record(
            'dashboard.quick_utilities.delete',
            'Xoá tiện ích nhanh "'.$utilityKey.'"',
            $request->user(),
        );

        return response()->json([
            'utilities' => $this->service->forAdminListing(),
        ]);
    }

    public function reorder(ReorderDashboardQuickUtilitiesRequest $request): JsonResponse
    {
        try {
            $this->service->reorder($request->validated('items'), $request->user()?->id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->activityLogs->record(
            'dashboard.quick_utilities.reorder',
            'Sắp xếp lại tiện ích nhanh Dashboard của tôi',
            $request->user(),
        );

        return response()->json([
            'utilities' => $this->service->forAdminListing(),
        ]);
    }
}
