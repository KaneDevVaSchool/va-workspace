<?php

namespace Modules\Evaluation\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Evaluation\App\Http\Requests\StoreEvaluationPositionRequest;
use Modules\Evaluation\App\Http\Requests\UpdateEvaluationPositionRequest;
use Modules\Evaluation\App\Services\EvaluationPositionService;
use Modules\Identity\App\Services\PermissionService;

/**
 * Manager JSON (middleware web + session, bọc qua EvaluationServiceProvider):
 *   GET    /api/evaluation/positions        — danh mục dùng chung, mọi người có evaluation.manage_department xem được
 *   POST   /api/evaluation/positions        — tạo (evaluation.manage_department, department_director trở lên)
 *   PUT    /api/evaluation/positions/{id}
 *   DELETE /api/evaluation/positions/{id}
 *
 * "Vị trí đánh giá" là danh mục DÙNG CHUNG toàn hệ thống (không scoped theo
 * department_id, khác evaluation_criteria) — ai có quyền evaluation.manage_department
 * ở phòng ban mình đều tạo/sửa/xoá được, vì đây là danh mục chức danh chung,
 * không phải tài sản riêng của 1 phòng ban. Xem plans/2026-08-26-mau-danh-gia.md.
 */
class EvaluationPositionController extends Controller
{
    public function __construct(
        private readonly EvaluationPositionService $service,
        private readonly PermissionService $permissions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', (int) $request->user()?->department_id)) {
            return response()->json(['message' => 'Bạn không có quyền xem vị trí đánh giá.'], 403);
        }

        return response()->json(['positions' => $this->service->list()]);
    }

    public function store(StoreEvaluationPositionRequest $request): JsonResponse
    {
        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', (int) $request->user()?->department_id)) {
            return response()->json(['message' => 'Bạn không có quyền tạo vị trí đánh giá.'], 403);
        }

        $position = $this->service->create((int) $request->user()->id, $request->validated());

        return response()->json(['position' => $this->service->present($position)], 201);
    }

    public function update(UpdateEvaluationPositionRequest $request, int $id): JsonResponse
    {
        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', (int) $request->user()?->department_id)) {
            return response()->json(['message' => 'Bạn không có quyền cập nhật vị trí đánh giá.'], 403);
        }

        $position = $this->service->findOrFail($id);
        if ($position instanceof JsonResponse) {
            return $position;
        }

        $updated = $this->service->update($position, $request->validated());

        return response()->json(['position' => $this->service->present($updated)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', (int) $request->user()?->department_id)) {
            return response()->json(['message' => 'Bạn không có quyền xoá vị trí đánh giá.'], 403);
        }

        $position = $this->service->findOrFail($id);
        if ($position instanceof JsonResponse) {
            return $position;
        }

        $this->service->delete($position);

        return response()->json(['message' => 'Đã xoá vị trí đánh giá.']);
    }
}
