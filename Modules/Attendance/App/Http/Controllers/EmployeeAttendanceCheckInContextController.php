<?php

namespace Modules\Attendance\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Attendance\App\Services\EmployeeAttendanceCheckInContextService;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;

class EmployeeAttendanceCheckInContextController extends Controller
{
    public function __construct(
        private readonly EmployeeAttendanceCheckInContextService $context,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            $row = $this->context->forUser($request->user());
        } catch (HrmApiUnavailable $e) {
            if ($e->isConnectionFailure()) {
                return response()->json([
                    'message' => 'Không tải được cấu hình chấm công từ HRM. Thử lại sau.',
                    'detail' => config('app.debug') ? $e->getMessage() : null,
                ], 503);
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $row]);
    }
}
