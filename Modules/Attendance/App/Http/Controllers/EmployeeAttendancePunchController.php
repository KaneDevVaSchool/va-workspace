<?php

namespace Modules\Attendance\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Attendance\App\Services\EmployeeAttendancePunchService;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;

/**
 * Chấm công nhân viên — đẩy giờ vào/ra về HRM.
 * Giờ do server ghi. Trình duyệt chỉ gửi chiều (vào/ra) và hình thức.
 */
class EmployeeAttendancePunchController extends Controller
{
    public function __construct(
        private readonly EmployeeAttendancePunchService $punches,
    ) {}

    public function today(Request $request): JsonResponse
    {
        try {
            $row = $this->punches->today($request->user());
        } catch (HrmApiUnavailable $e) {
            return $this->failure($e, 'Không tải được giờ chấm công từ HRM.');
        }

        return response()->json(['data' => $row]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'direction' => ['required', Rule::in(['in', 'out'])],
            'method' => ['required', 'string', 'max:64'],
        ], [
            'direction.required' => 'Chưa xác định chấm vào hay chấm ra.',
            'direction.in' => 'Chỉ chấm vào hoặc chấm ra.',
            'method.required' => 'Chưa chọn hình thức chấm công.',
            'method.max' => 'Hình thức chấm công quá dài.',
        ]);

        $method = trim($validated['method']);
        if ($method === '') {
            return response()->json(['message' => 'Chưa chọn hình thức chấm công.'], 422);
        }

        try {
            $row = $this->punches->punch($request->user(), $validated['direction'], $method);
        } catch (HrmApiUnavailable $e) {
            return $this->failure($e, 'Không gửi được giờ chấm công về HRM.');
        }

        return response()->json([
            'message' => 'Đã gửi giờ chấm công về HRM.',
            'data' => $row,
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
