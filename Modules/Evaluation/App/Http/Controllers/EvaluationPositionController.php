<?php

namespace Modules\Evaluation\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Evaluation\App\Services\EvaluationPositionService;
use Modules\Identity\App\Services\PermissionService;

/**
 * Manager JSON (middleware web + session, bọc qua EvaluationServiceProvider):
 *   GET /api/evaluation/positions — danh mục dùng chung, mọi người có evaluation.manage_department xem được
 *
 * "Vị trí đánh giá" là danh mục DÙNG CHUNG toàn hệ thống (không scoped theo
 * department_id, khác evaluation_criteria) — CHỈ ĐỌC, không có tạo/sửa/xoá
 * tay ở đây nữa. Danh mục sẽ nối API VA-HRM sau này (định danh HRM lưu vào
 * cột hrm_position_uuid đã có sẵn ở bảng evaluation_positions).
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
}
