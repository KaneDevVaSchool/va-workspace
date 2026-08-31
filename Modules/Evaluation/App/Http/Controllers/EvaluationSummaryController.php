<?php

namespace Modules\Evaluation\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Evaluation\App\Http\Requests\EvaluationSummaryRequest;
use Modules\Evaluation\App\Services\EvaluationSummaryService;
use Modules\Identity\App\Services\PermissionService;

/**
 * Manager JSON:
 *   GET /api/evaluation/summary?from=&to=  — bảng tổng hợp đánh giá cả phòng
 *                                            ban trong kỳ
 */
class EvaluationSummaryController extends Controller
{
    public function __construct(
        private readonly EvaluationSummaryService $service,
        private readonly PermissionService $permissions,
    ) {}

    public function index(EvaluationSummaryRequest $request): JsonResponse
    {
        $departmentId = $request->user()?->department_id;

        if (! $departmentId) {
            return response()->json(['message' => 'Tài khoản chưa gắn với phòng ban nào.'], 422);
        }

        $departmentId = (int) $departmentId;

        if (! $this->permissions->allows(
            $request->user(),
            'evaluation.manage_department',
            'department',
            $departmentId,
        )) {
            return response()->json(['message' => 'Bạn không có quyền xem tổng hợp đánh giá.'], 403);
        }

        $validated = $request->validated();

        return response()->json($this->service->summarize(
            $departmentId,
            (string) $validated['from'],
            (string) $validated['to'],
        ));
    }
}
