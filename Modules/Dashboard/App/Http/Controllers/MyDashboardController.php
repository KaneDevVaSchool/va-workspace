<?php

namespace Modules\Dashboard\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Dashboard\App\Services\MyDashboardDirectoryService;
use Modules\Dashboard\App\Services\MyDashboardService;
use Modules\Identity\App\Hrm\Exceptions\HrmDatabaseUnavailable;

/**
 * Dashboard cá nhân — LUÔN scope theo chính người đăng nhập, không nhận bất
 * kỳ tham số user/department nào từ request (khác DepartmentDashboardController
 * vốn cho phép viewer có quyền toàn cục xem phòng ban khác qua department_id).
 */
class MyDashboardController extends Controller
{
    public function __construct(
        private readonly MyDashboardService $service,
        private readonly MyDashboardDirectoryService $directory,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        return response()->json($this->service->overview($request->user()->id));
    }

    public function directory(): JsonResponse
    {
        try {
            return response()->json($this->directory->listing());
        } catch (HrmDatabaseUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }
}
