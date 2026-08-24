<?php

namespace Modules\Evaluation\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Evaluation\App\Http\Requests\StoreEvaluationCriteriaRequest;
use Modules\Evaluation\App\Http\Requests\UpdateEvaluationCriteriaRequest;
use Modules\Evaluation\App\Services\EvaluationCriteriaService;
use Modules\Identity\App\Services\ActivityLogService;
use Modules\Identity\App\Services\PermissionService;

/**
 * Manager JSON (middleware web + session, bọc qua EvaluationServiceProvider):
 *   GET    /api/evaluation/criteria                — list tiêu chí phòng ban user
 *   GET    /api/evaluation/criteria?department_id= — list cho superadmin (workspace_config.view_all)
 *   POST   /api/evaluation/criteria                — tạo tiêu chí mới
 *   PUT    /api/evaluation/criteria/{id}           — cập nhật tiêu chí
 *   DELETE /api/evaluation/criteria/{id}           — xoá tiêu chí
 *   PATCH  /api/evaluation/criteria/{id}/toggle    — bật/tắt is_active
 *
 * department_id luôn lấy từ user (manager route) — trưởng phòng chỉ xem/sửa PB mình.
 * Ngoại lệ: GET với ?department_id có thể dùng cho superadmin (kiểm tra workspace_config.view_all).
 */
class EvaluationCriteriaController extends Controller
{
    public function __construct(
        private readonly EvaluationCriteriaService $service,
        private readonly PermissionService $permissions,
        private readonly ActivityLogService $activityLogs,
    ) {}

    private function departmentIdOrFail(Request $request): int|JsonResponse
    {
        $departmentId = $request->user()?->department_id;

        return $departmentId
            ? (int) $departmentId
            : response()->json(['message' => 'Tài khoản chưa gắn với phòng ban nào.'], 422);
    }

    public function index(Request $request): JsonResponse
    {
        // Superadmin đọc tiêu chí của phòng ban bất kỳ qua ?department_id=
        $queryDeptId = $request->query('department_id');

        if ($queryDeptId !== null) {
            if (! $this->permissions->allows($request->user(), 'workspace_config.view_all')) {
                return response()->json(['message' => 'Không có quyền xem tiêu chí phòng ban khác.'], 403);
            }

            return response()->json([
                'criteria' => $this->service->listForDepartment((int) $queryDeptId),
            ]);
        }

        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        return response()->json([
            'criteria' => $this->service->listForDepartment($departmentId),
        ]);
    }

    public function store(StoreEvaluationCriteriaRequest $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền tạo tiêu chí đánh giá.'], 403);
        }

        $criterion = $this->service->create(
            $departmentId,
            (int) $request->user()->id,
            $request->validated(),
        );

        $this->activityLogs->record(
            'evaluation_criteria.create',
            'Tạo tiêu chí đánh giá "'.$criterion->name.'"',
            $request->user(),
            'evaluation_criteria',
            $criterion->id,
        );

        return response()->json(['criterion' => $this->service->present($criterion)], 201);
    }

    public function update(UpdateEvaluationCriteriaRequest $request, int $id): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền cập nhật tiêu chí đánh giá.'], 403);
        }

        $criterion = $this->service->findByDepartmentOrFail($id, $departmentId);
        if ($criterion instanceof JsonResponse) {
            return $criterion;
        }

        $updated = $this->service->update($criterion, $request->validated());

        $this->activityLogs->record(
            'evaluation_criteria.update',
            'Cập nhật tiêu chí đánh giá "'.$updated->name.'"',
            $request->user(),
            'evaluation_criteria',
            $updated->id,
        );

        return response()->json(['criterion' => $this->service->present($updated)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền xoá tiêu chí đánh giá.'], 403);
        }

        $criterion = $this->service->findByDepartmentOrFail($id, $departmentId);
        if ($criterion instanceof JsonResponse) {
            return $criterion;
        }

        $name = $criterion->name;
        $this->service->delete($criterion);

        $this->activityLogs->record(
            'evaluation_criteria.delete',
            'Xoá tiêu chí đánh giá "'.$name.'"',
            $request->user(),
            'evaluation_criteria',
            $id,
        );

        return response()->json(['message' => 'Đã xoá tiêu chí đánh giá.']);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền cập nhật tiêu chí đánh giá.'], 403);
        }

        $criterion = $this->service->findByDepartmentOrFail($id, $departmentId);
        if ($criterion instanceof JsonResponse) {
            return $criterion;
        }

        $updated = $this->service->toggleActive($criterion);

        return response()->json(['criterion' => $this->service->present($updated)]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền sắp xếp tiêu chí đánh giá.'], 403);
        }

        $ids = $request->input('ids', []);
        if (! is_array($ids)) {
            return response()->json(['message' => 'Danh sách IDs không hợp lệ.'], 422);
        }

        $this->service->reorder($departmentId, array_map('intval', $ids));

        return response()->json(['message' => 'Đã cập nhật thứ tự tiêu chí.']);
    }
}
