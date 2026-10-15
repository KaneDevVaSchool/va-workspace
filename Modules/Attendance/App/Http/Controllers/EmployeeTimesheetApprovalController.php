<?php

namespace Modules\Attendance\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Attendance\App\Services\EmployeeTimesheetApprovalService;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;

/**
 * Trang «Duyệt bảng công» — Trưởng đơn vị xem / duyệt / từ chối bảng chấm công HRM.
 */
class EmployeeTimesheetApprovalController extends Controller
{
    public function __construct(
        private readonly EmployeeTimesheetApprovalService $approvals,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope' => ['nullable', Rule::in(['pending', 'history'])],
        ]);

        try {
            $items = $this->approvals->listForUser($request->user(), $validated['scope'] ?? 'pending');
        } catch (HrmApiUnavailable $e) {
            return $this->failure($e, 'Không tải được danh sách bảng chấm công từ HRM.');
        }

        return response()->json(['data' => $items]);
    }

    public function count(Request $request): JsonResponse
    {
        return response()->json(['count' => $this->approvals->pendingCount($request->user())]);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        try {
            $item = $this->approvals->detailForUser($request->user(), $uuid);
        } catch (HrmApiUnavailable $e) {
            return $this->failure($e, 'Không tải được bảng chấm công từ HRM.');
        }

        if ($item === null) {
            return response()->json(['message' => 'Không tìm thấy bảng chấm công.'], 404);
        }

        return response()->json(['data' => $item]);
    }

    public function approve(Request $request, string $uuid): JsonResponse
    {
        try {
            $item = $this->approvals->decideForUser($request->user(), $uuid, true);
        } catch (HrmApiUnavailable $e) {
            return $this->failure($e, 'Không duyệt được bảng chấm công.');
        }

        return response()->json([
            'message' => 'Đã duyệt — bảng chấm công chuyển sang HCNS kiểm tra.',
            'data' => $item,
        ]);
    }

    public function reject(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do từ chối.',
            'reason.min' => 'Lý do từ chối cần tối thiểu 5 ký tự.',
            'reason.max' => 'Lý do từ chối không được vượt quá 1000 ký tự.',
        ]);

        try {
            $item = $this->approvals->decideForUser($request->user(), $uuid, false, trim($validated['reason']));
        } catch (HrmApiUnavailable $e) {
            return $this->failure($e, 'Không từ chối được bảng chấm công.');
        }

        return response()->json([
            'message' => 'Đã từ chối — bảng chấm công trả về Admin đơn vị.',
            'data' => $item,
        ]);
    }

    private function failure(HrmApiUnavailable $e, string $fallback): JsonResponse
    {
        if ($e->isConnectionFailure()) {
            return response()->json([
                'message' => $fallback.' Thử lại sau.',
                'detail' => config('app.debug') ? $e->getMessage() : null,
            ], 503);
        }

        return response()->json(['message' => $e->getMessage()], 422);
    }
}
