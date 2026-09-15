<?php

namespace Modules\FeatureRequest\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\FeatureRequest\App\Http\Requests\ApproveFeatureRequestRequest;
use Modules\FeatureRequest\App\Http\Requests\RejectFeatureRequestRequest;
use Modules\FeatureRequest\App\Http\Requests\StoreFeatureRequestRequest;
use Modules\FeatureRequest\App\Http\Requests\UpdateFeatureRequestRequest;
use Modules\FeatureRequest\App\Models\FeatureRequest;
use Modules\FeatureRequest\App\Services\FeatureRequestService;
use Modules\Identity\App\Services\ActivityLogService;
use Modules\Identity\App\Services\PermissionService;

/**
 * JSON dưới prefix /api:
 *   GET    /api/feature-requests/mine                    — ghi nhận của bản thân
 *   POST   /api/feature-requests                          — gửi ghi nhận mới
 *   PUT    /api/feature-requests/{id}                     — sửa (chỉ khi chờ ghi nhận)
 *   DELETE /api/feature-requests/{id}                     — xoá (chỉ khi chờ ghi nhận)
 *   GET    /api/superadmin/feature-requests                — danh sách nhóm theo phòng ban
 *   GET    /api/superadmin/feature-requests/{id}           — chi tiết (tự chuyển "đang xem xét")
 *   PATCH  /api/superadmin/feature-requests/{id}/approve   — duyệt
 *   PATCH  /api/superadmin/feature-requests/{id}/reject    — từ chối
 *   PATCH  /api/superadmin/feature-requests/{id}/done      — đánh dấu hoàn thành
 */
class FeatureRequestController extends Controller
{
    public function __construct(
        private readonly FeatureRequestService $service,
        private readonly PermissionService $permissions,
        private readonly ActivityLogService $activityLogs,
    ) {}

    public function mine(Request $request): JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Bạn cần đăng nhập.'], 401);
        }

        return response()->json(['items' => $this->service->ownList($request->user())]);
    }

    public function store(StoreFeatureRequestRequest $request): JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Bạn cần đăng nhập.'], 401);
        }

        if (! $this->permissions->allows($request->user(), 'feature_request.create')) {
            return response()->json(['message' => 'Bạn không có quyền ghi nhận yêu cầu tính năng.'], 403);
        }

        $item = $this->service->create($request->user(), $request->validated());

        $this->activityLogs->record(
            'feature_request.create',
            'Ghi nhận yêu cầu tính năng mới',
            $request->user(),
            'feature_request',
            (int) $item->id,
        );

        return response()->json(['item' => $this->service->present($item)], 201);
    }

    public function update(UpdateFeatureRequestRequest $request, int $id): JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Bạn cần đăng nhập.'], 401);
        }

        $item = $this->service->find($id);
        if ($item === null) {
            return response()->json(['message' => 'Không tìm thấy ghi nhận.'], 404);
        }

        $item = $this->service->updateOwn($item, $request->user(), $request->validated());

        $this->activityLogs->record(
            'feature_request.update',
            'Cập nhật ghi nhận yêu cầu tính năng',
            $request->user(),
            'feature_request',
            (int) $item->id,
        );

        return response()->json(['item' => $this->service->present($item)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Bạn cần đăng nhập.'], 401);
        }

        $item = $this->service->find($id);
        if ($item === null) {
            return response()->json(['message' => 'Không tìm thấy ghi nhận.'], 404);
        }

        $this->service->deleteOwn($item, $request->user());

        $this->activityLogs->record(
            'feature_request.delete',
            'Xoá ghi nhận yêu cầu tính năng',
            $request->user(),
            'feature_request',
            $id,
        );

        return response()->json(['deleted' => true]);
    }

    public function indexForSuperAdmin(Request $request): JsonResponse
    {
        if (! $this->allowedReview($request)) {
            return response()->json(['message' => 'Bạn không có quyền xem ghi nhận yêu cầu tính năng.'], 403);
        }

        $status = $request->query('status');

        return response()->json(['groups' => $this->service->groupedByDepartment(is_string($status) ? $status : null)]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $result = $this->itemOrFail($request, $id);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        $item = $this->service->markReviewingIfPending($result);

        return response()->json(['item' => $this->service->present($item)]);
    }

    public function approve(ApproveFeatureRequestRequest $request, int $id): JsonResponse
    {
        $result = $this->itemOrFail($request, $id);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        $data = $request->validated();
        $item = $this->service->approve(
            $result,
            $request->user(),
            $data['expected_done_at'] ?? null,
            $data['progress_note'] ?? null,
        );

        $this->activityLogs->record(
            'feature_request.approve',
            'Duyệt yêu cầu tính năng',
            $request->user(),
            'feature_request',
            (int) $item->id,
        );

        return response()->json(['item' => $this->service->present($item)]);
    }

    public function reject(RejectFeatureRequestRequest $request, int $id): JsonResponse
    {
        $result = $this->itemOrFail($request, $id);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        $item = $this->service->reject($result, $request->user(), (string) $request->validated()['reject_reason']);

        $this->activityLogs->record(
            'feature_request.reject',
            'Từ chối yêu cầu tính năng',
            $request->user(),
            'feature_request',
            (int) $item->id,
        );

        return response()->json(['item' => $this->service->present($item)]);
    }

    public function markDone(Request $request, int $id): JsonResponse
    {
        $result = $this->itemOrFail($request, $id);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        $item = $this->service->markDone($result, $request->user());

        $this->activityLogs->record(
            'feature_request.done',
            'Đánh dấu hoàn thành yêu cầu tính năng',
            $request->user(),
            'feature_request',
            (int) $item->id,
        );

        return response()->json(['item' => $this->service->present($item)]);
    }

    private function itemOrFail(Request $request, int $id): FeatureRequest|JsonResponse
    {
        if (! $this->allowedReview($request)) {
            return response()->json(['message' => 'Bạn không có quyền xử lý yêu cầu tính năng.'], 403);
        }

        $item = $this->service->find($id);
        if ($item === null) {
            return response()->json(['message' => 'Không tìm thấy ghi nhận.'], 404);
        }

        return $item;
    }

    private function allowedReview(Request $request): bool
    {
        return $request->user() !== null && $this->permissions->allows($request->user(), 'feature_request.review');
    }
}
