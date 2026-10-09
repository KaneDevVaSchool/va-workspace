<?php

namespace Modules\Attendance\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Attendance\App\Services\EmployeeLeaveWorkflowService;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;

/**
 * Luồng duyệt / HR theo dõi cho form đơn nghỉ (proxy logic HRM).
 */
class EmployeeLeaveWorkflowController extends Controller
{
    public function show(Request $request, EmployeeLeaveWorkflowService $workflow): JsonResponse
    {
        try {
            $payload = $workflow->forUser($request->user());
        } catch (HrmApiUnavailable $e) {
            return response()->json([
                'message' => 'Không kết nối được HRM để lấy người duyệt.',
                'detail' => config('app.debug') ? $e->getMessage() : null,
            ], 503);
        }

        return response()->json(['data' => $payload]);
    }
}
