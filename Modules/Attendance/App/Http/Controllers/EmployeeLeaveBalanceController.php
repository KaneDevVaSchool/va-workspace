<?php

namespace Modules\Attendance\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Attendance\App\Services\EmployeeLeaveBalanceService;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;

class EmployeeLeaveBalanceController extends Controller
{
    public function show(Request $request, EmployeeLeaveBalanceService $balances): JsonResponse
    {
        $year = $request->filled('year') ? (int) $request->integer('year') : null;

        try {
            $data = $balances->overviewForUser($request->user(), $year);
        } catch (HrmApiUnavailable $e) {
            if (! $e->isConnectionFailure()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return response()->json([
                'message' => 'Không tải được số dư phép từ HRM.',
                'detail' => config('app.debug') ? $e->getMessage() : null,
            ], 503);
        }

        return response()->json(['data' => $data]);
    }
}
