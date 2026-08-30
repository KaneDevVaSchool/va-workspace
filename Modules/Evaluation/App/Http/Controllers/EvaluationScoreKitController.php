<?php

namespace Modules\Evaluation\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Evaluation\App\Http\Requests\UpdateEvaluationScoreKitRequest;
use Modules\Evaluation\App\Services\EvaluationScoreKitService;
use Modules\Identity\App\Services\ActivityLogService;
use Modules\Identity\App\Services\PermissionService;

/**
 * Manager JSON:
 *   GET  /api/evaluation/score-kit  — khung chấm điểm phòng ban user
 *   PUT  /api/evaluation/score-kit  — lưu cách tính (evaluation.manage_department)
 */
class EvaluationScoreKitController extends Controller
{
    public function __construct(
        private readonly EvaluationScoreKitService $service,
        private readonly PermissionService $permissions,
        private readonly ActivityLogService $activityLogs,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền xem khung chấm điểm.'], 403);
        }

        return response()->json($this->service->showForDepartment($departmentId));
    }

    public function update(UpdateEvaluationScoreKitRequest $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền cập nhật khung chấm điểm.'], 403);
        }

        $kit = $this->service->upsert(
            $departmentId,
            (int) $request->user()->id,
            $request->validated(),
        );

        $this->activityLogs->record(
            'evaluation_score_kit.update',
            $this->activityDescription($kit),
            $request->user(),
            'evaluation_score_kit',
            $kit['id'] !== null ? (int) $kit['id'] : null,
            [
                'department_id' => $departmentId,
                'mode' => $kit['mode'],
            ],
        );

        return response()->json(['kit' => $kit]);
    }

    private function departmentIdOrFail(Request $request): int|JsonResponse
    {
        $departmentId = $request->user()?->department_id;

        return $departmentId
            ? (int) $departmentId
            : response()->json(['message' => 'Tài khoản chưa gắn với phòng ban nào.'], 422);
    }

    /** @param  array<string, mixed>  $kit */
    private function activityDescription(array $kit): string
    {
        $mode = $kit['mode'] ?? null;
        if ($mode === 'base_adjust') {
            return 'Chọn cách tính điểm gốc ± theo việc';
        }
        if ($mode === 'weighted_task') {
            return 'Chọn cách tính theo trọng số việc và dự án';
        }

        return 'Cập nhật khung chấm điểm';
    }
}
