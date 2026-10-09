<?php

namespace Modules\Attendance\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;
use Modules\Identity\App\Hrm\Services\HrmApiClient;

/**
 * Proxy catalog nghỉ phép (GET /api/v1/leave/types) — mặc định portal HRM
 * (HRM_LEAVE_API_*), sau chuyển về hrm.vaschools.edu.vn.
 */
class EmployeeLeaveCatalogController extends Controller
{
    public function leaveTypes(HrmApiClient $hrmApi): JsonResponse
    {
        try {
            $items = $hrmApi->listLeaveTypes();
        } catch (HrmApiUnavailable $e) {
            return response()->json([
                'message' => 'Không kết nối được cấu hình nghỉ phép từ HRM.',
                'detail' => config('app.debug') ? $e->getMessage() : null,
            ], 503);
        }

        return response()->json(['data' => $items]);
    }
}
