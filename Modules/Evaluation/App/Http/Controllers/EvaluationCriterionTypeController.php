<?php

namespace Modules\Evaluation\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Evaluation\App\Http\Requests\StoreEvaluationCriterionTypeRequest;
use Modules\Evaluation\App\Services\EvaluationCriterionTypeService;
use Modules\Evaluation\App\Services\EvaluationDepartmentResolver;
use Modules\Identity\App\Services\ActivityLogService;
use Modules\Identity\App\Services\PermissionService;

class EvaluationCriterionTypeController extends Controller
{
    public function __construct(
        private readonly EvaluationCriterionTypeService $service,
        private readonly PermissionService $permissions,
        private readonly ActivityLogService $activityLogs,
        private readonly EvaluationDepartmentResolver $departments,
    ) {}

    private function departmentIdOrFail(Request $request): int|JsonResponse
    {
        return $this->departments->idOrFail($request);
    }

    public function index(Request $request): JsonResponse
    {
        $departmentId = $this->departments->resolveCriterionTypesForRead($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        return response()->json([
            'types' => $this->service->listForDepartment($departmentId),
        ]);
    }

    public function store(StoreEvaluationCriterionTypeRequest $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền tạo loại tiêu chí.'], 403);
        }

        $type = $this->service->create(
            $departmentId,
            (int) $request->user()->id,
            $request->validated(),
        );

        $this->activityLogs->record(
            'evaluation_criterion_type.create',
            'Tạo loại tiêu chí đánh giá "'.$type->name.'"',
            $request->user(),
            'evaluation_criterion_type',
            $type->id,
        );

        return response()->json(['type' => $this->service->present($type)], 201);
    }
}
