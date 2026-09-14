<?php

namespace Modules\Dashboard\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Dashboard\App\Services\MyDashboardService;

/**
 * Dashboard cá nhân — LUÔN scope theo chính người đăng nhập, không nhận bất
 * kỳ tham số user/department nào từ request (khác DepartmentDashboardController
 * vốn cho phép viewer có quyền toàn cục xem phòng ban khác qua department_id).
 */
class MyDashboardController extends Controller
{
    public function __construct(private readonly MyDashboardService $service) {}

    public function overview(Request $request): JsonResponse
    {
        return response()->json($this->service->overview($request->user()->id));
    }
}
