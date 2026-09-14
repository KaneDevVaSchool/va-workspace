<?php

namespace Modules\Dashboard\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Dashboard\App\Http\Requests\CompanyOverviewRequest;
use Modules\Dashboard\App\Http\Requests\CompanyProjectsListRequest;
use Modules\Dashboard\App\Services\CompanyDashboardService;

/**
 * Controller mỏng: chỉ nhận request, gọi Service, trả response. Quyền truy
 * cập chặn ở route (permission:dashboard.view_company) — dữ liệu ở đây là
 * toàn công ty, không cần thu hẹp phạm vi theo viewer.
 */
class CompanyDashboardController extends Controller
{
    public function __construct(private readonly CompanyDashboardService $service) {}

    public function overview(CompanyOverviewRequest $request): JsonResponse
    {
        $filters = $request->only(['department_id', 'date_from', 'date_to', 'months_before', 'months_after']);

        return response()->json($this->service->overview($filters));
    }

    public function projects(CompanyProjectsListRequest $request): JsonResponse
    {
        $filters = $request->only(['status', 'department_id', 'health', 'overdue_bucket', 'overdue_only', 'q', 'sort_by', 'sort_dir']);
        $perPage = (int) $request->input('per_page', 20);
        $page = (int) $request->input('page', 1);

        return response()->json($this->service->projects($filters, $perPage, $page));
    }

    public function projectDetail(int $project): JsonResponse
    {
        $detail = $this->service->projectDetail($project);

        if ($detail === null) {
            return response()->json(['message' => 'Không tìm thấy dự án.'], 404);
        }

        return response()->json($detail);
    }
}
