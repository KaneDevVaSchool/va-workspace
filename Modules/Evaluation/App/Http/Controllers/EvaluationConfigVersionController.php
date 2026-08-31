<?php

namespace Modules\Evaluation\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Evaluation\App\Http\Requests\PublishEvaluationConfigVersionRequest;
use Modules\Evaluation\App\Services\EvaluationConfigVersionService;
use Modules\Identity\App\Services\ActivityLogService;
use Modules\Identity\App\Services\PermissionService;

/**
 * Manager JSON:
 *   GET  /api/evaluation/config-versions          — lịch sử phiên bản phòng ban
 *   GET  /api/evaluation/config-versions/active   — phiên bản đang áp dụng
 *   POST /api/evaluation/config-versions/publish  — chốt phiên bản mới
 *   GET  /api/evaluation/config-versions/{id}     — chi tiết kèm bản chụp
 *
 * department_id luôn lấy từ user đăng nhập — trưởng phòng chỉ thao tác phòng mình.
 */
class EvaluationConfigVersionController extends Controller
{
    public function __construct(
        private readonly EvaluationConfigVersionService $service,
        private readonly PermissionService $permissions,
        private readonly ActivityLogService $activityLogs,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->allowed($request, $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền xem phiên bản khung chấm điểm.'], 403);
        }

        return response()->json([
            'versions' => $this->service->listForDepartment($departmentId)
                ->map(fn ($version) => $this->service->present($version))
                ->values()
                ->all(),
        ]);
    }

    public function active(Request $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->allowed($request, $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền xem phiên bản khung chấm điểm.'], 403);
        }

        $version = $this->service->activeForDepartment($departmentId);

        return response()->json([
            'version' => $version !== null ? $this->service->present($version) : null,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->allowed($request, $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền xem phiên bản khung chấm điểm.'], 403);
        }

        $version = $this->service->find($id);
        if ($version === null || (int) $version->department_id !== $departmentId) {
            return response()->json(['message' => 'Không tìm thấy phiên bản khung chấm điểm.'], 404);
        }

        return response()->json([
            'version' => $this->service->present($version, true),
        ]);
    }

    public function publish(PublishEvaluationConfigVersionRequest $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->allowed($request, $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền chốt phiên bản khung chấm điểm.'], 403);
        }

        $data = $request->validated();
        $version = $this->service->publish(
            $departmentId,
            (int) $request->user()->id,
            $data['notes'] ?? null,
            $data['effective_from'] ?? null,
        );

        $this->activityLogs->record(
            'evaluation_config_version.publish',
            'Chốt phiên bản khung chấm điểm số '.$version->version_no,
            $request->user(),
            'evaluation_config_version',
            (int) $version->id,
            [
                'department_id' => $departmentId,
                'version_no' => $version->version_no,
                'criteria_count' => count($version->criteria_snapshot ?? []),
            ],
        );

        return response()->json([
            'version' => $this->service->present($version, true),
        ], 201);
    }

    private function allowed(Request $request, int $departmentId): bool
    {
        return $this->permissions->allows(
            $request->user(),
            'evaluation.manage_department',
            'department',
            $departmentId,
        );
    }

    private function departmentIdOrFail(Request $request): int|JsonResponse
    {
        $departmentId = $request->user()?->department_id;

        return $departmentId
            ? (int) $departmentId
            : response()->json(['message' => 'Tài khoản chưa gắn với phòng ban nào.'], 422);
    }
}
