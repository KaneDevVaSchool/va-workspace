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
            'session' => ['required', Rule::in(['morning', 'afternoon'])],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'method' => ['required', 'string', 'max:64'],
        ], [
            'session.required' => 'Chưa xác định ca chấm công.',
            'session.in' => 'Ca chấm công phải là sáng hoặc chiều.',
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
            $row = $this->punches->punch(
                $request->user(),
                $validated['session'],
                $validated['direction'],
                $method,
            );
        } catch (HrmApiUnavailable $e) {
            return $this->failure($e, 'Không gửi được giờ chấm công về HRM.');
        }

        return response()->json([
            'message' => 'Đã gửi giờ chấm công về HRM.',
            'data' => $row,
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'morning_check_out_time' => ['nullable', 'date_format:H:i'],
            'afternoon_check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'check_in_method' => ['nullable', 'string', 'max:64'],
            'morning_check_out_method' => ['nullable', 'string', 'max:64'],
            'afternoon_check_in_method' => ['nullable', 'string', 'max:64'],
            'check_out_method' => ['nullable', 'string', 'max:64'],
        ], [
            'check_in_time.date_format' => 'Giờ vào sáng không đúng định dạng.',
            'morning_check_out_time.date_format' => 'Giờ ra sáng không đúng định dạng.',
            'afternoon_check_in_time.date_format' => 'Giờ vào chiều không đúng định dạng.',
            'check_out_time.date_format' => 'Giờ ra chiều không đúng định dạng.',
        ]);

        $hasTime = collect([
            'check_in_time',
            'morning_check_out_time',
            'afternoon_check_in_time',
            'check_out_time',
        ])->contains(fn (string $key) => filled($validated[$key] ?? null));

        if (! $hasTime) {
            return response()->json(['message' => 'Chưa có giờ chấm để đồng bộ.'], 422);
        }

        try {
            $row = $this->punches->syncToday($request->user(), $validated);
        } catch (HrmApiUnavailable $e) {
            return $this->failure($e, 'Không đồng bộ được giờ chấm công lên HRM.');
        }

        return response()->json([
            'message' => 'Đã đồng bộ giờ chấm công lên HRM.',
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
