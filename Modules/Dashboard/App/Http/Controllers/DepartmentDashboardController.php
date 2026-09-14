<?php

namespace Modules\Dashboard\App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Dashboard\App\Http\Requests\DepartmentEmployeesListRequest;
use Modules\Dashboard\App\Http\Requests\DepartmentOverviewRequest;
use Modules\Dashboard\App\Services\DepartmentDashboardService;

/**
 * Controller mỏng: chỉ nhận request, gọi Service, trả response.
 *
 * Quyền middleware route (performance.view_department|project.view) chỉ đảm
 * bảo viewer được xem MỘT dashboard phòng ban nào đó — phạm vi chính xác
 * (đúng phòng ban nào) phải tự chặn ở đây: mặc định luôn dùng phòng ban của
 * chính viewer; chỉ ai có quyền toàn cục (super_admin, dashboard.view_company,
 * hoặc project.*) mới được truyền department_id để xem phòng ban khác.
 */
class DepartmentDashboardController extends Controller
{
    public function __construct(private readonly DepartmentDashboardService $service) {}

    public function overview(DepartmentOverviewRequest $request): JsonResponse
    {
        $user = $request->user();
        $departmentId = $this->resolveDepartmentId($request, $user);
        if ($departmentId === null) {
            return response()->json(['message' => 'Bạn chưa thuộc phòng ban nào để xem dashboard này.'], 422);
        }

        $teamId = $request->filled('team_id') ? (int) $request->input('team_id') : null;

        return response()->json($this->service->overview($departmentId, $teamId));
    }

    public function employees(DepartmentEmployeesListRequest $request): JsonResponse
    {
        $user = $request->user();
        $departmentId = $this->resolveDepartmentId($request, $user);
        if ($departmentId === null) {
            return response()->json(['message' => 'Bạn chưa thuộc phòng ban nào để xem dashboard này.'], 422);
        }

        $teamId = $request->filled('team_id') ? (int) $request->input('team_id') : null;
        $filters = $request->only(['status', 'q']);
        $perPage = (int) $request->input('per_page', 20);
        $page = (int) $request->input('page', 1);

        return response()->json($this->service->employees($departmentId, $teamId, $filters, $perPage, $page));
    }

    public function employeeDetail(Request $request, int $user): JsonResponse
    {
        $viewer = $request->user();
        $departmentId = $this->resolveDepartmentId($request, $viewer);
        if ($departmentId === null) {
            return response()->json(['message' => 'Bạn chưa thuộc phòng ban nào để xem dashboard này.'], 422);
        }

        $detail = $this->service->employeeDetail($user, $departmentId);
        if ($detail === null) {
            return response()->json(['message' => 'Không tìm thấy nhân viên trong phạm vi phòng ban của bạn.'], 404);
        }

        return response()->json($detail);
    }

    /**
     * Trả department_id sẽ dùng cho request này, tự chặn phạm vi: viewer
     * không có quyền toàn cục mà truyền department_id khác phòng ban mình →
     * null (Controller trả 422/403 tương ứng — ở đây coi như không hợp lệ,
     * ép về phòng ban của chính họ để không rò rỉ dữ liệu).
     */
    private function resolveDepartmentId(Request $request, User $user): ?int
    {
        $requested = $request->filled('department_id') ? (int) $request->input('department_id') : null;
        $hasGlobalScope = $user->isSuperAdmin() || $user->allows('dashboard.view_company') || $user->allows('project.*');

        if ($requested !== null && $hasGlobalScope) {
            return $requested;
        }

        return $user->department_id;
    }
}
