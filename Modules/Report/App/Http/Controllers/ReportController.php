<?php

namespace Modules\Report\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\App\Services\ActivityLogService;
use Modules\Identity\App\Services\PermissionService;
use Modules\Report\App\Http\Requests\PreviewPersonnelEvaluationReportRequest;
use Modules\Report\App\Http\Requests\StorePersonnelEvaluationReportRequest;
use Modules\Report\App\Http\Requests\UpdateReportRequest;
use Modules\Report\App\Models\Report;
use Modules\Report\App\Services\ReportService;

/**
 * Manager JSON:
 *   GET    /api/report                          — danh sách báo cáo xem được
 *   POST   /api/report/personnel-evaluation     — tạo báo cáo đánh giá nhân sự
 *   GET    /api/report/{id}                     — kết quả báo cáo (tổng hợp + bảng)
 *   PUT    /api/report/{id}                     — sửa cấu hình
 *   PATCH  /api/report/{id}/save                — chốt lưu
 *   GET    /api/report/{id}/employees/{userId}  — chi tiết điểm một nhân sự
 *   DELETE /api/report/{id}                     — xoá
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $service,
        private readonly PermissionService $permissions,
        private readonly ActivityLogService $activityLogs,
    ) {}

    /**
     * Phân trang ở máy chủ — phòng ban vài nghìn báo cáo thì không tải hết về
     * trình duyệt rồi cắt trang bằng JavaScript được.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $result = $this->service->paginateVisible(
            $request->user(),
            [
                'report_type' => $request->query('report_type'),
                'q' => $request->query('q'),
            ],
            $perPage,
            max(1, (int) $request->query('page', 1)),
        );

        return response()->json([
            'reports' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    public function storePersonnelEvaluation(StorePersonnelEvaluationReportRequest $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'report.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền tạo báo cáo cho phòng ban này.'], 403);
        }

        $report = $this->service->createPersonnelEvaluation(
            $departmentId,
            $request->user(),
            $request->validated(),
        );

        $this->activityLogs->record(
            'report.create',
            'Tạo báo cáo "'.$report->title.'"',
            $request->user(),
            'report',
            (int) $report->id,
            ['department_id' => $departmentId, 'report_type' => $report->report_type],
        );

        return response()->json(['report' => $this->service->presentDetail($report)], 201);
    }

    /**
     * Xem trước số liệu ở bước cuối của wizard — không tạo báo cáo, không
     * chốt phiên bản mới.
     */
    public function previewPersonnelEvaluation(
        PreviewPersonnelEvaluationReportRequest $request,
    ): JsonResponse {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'report.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền xem trước báo cáo của phòng ban này.'], 403);
        }

        return response()->json(
            $this->service->previewPersonnelEvaluation($departmentId, $request->validated()),
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $report = $this->service->find($id);
        if ($report === null) {
            return response()->json(['message' => 'Không tìm thấy báo cáo.'], 404);
        }

        if (! $this->service->canView($report, $request->user())) {
            return response()->json(['message' => 'Bạn không có quyền xem báo cáo này.'], 403);
        }

        return response()->json($this->service->present($report));
    }

    public function employeeDetail(Request $request, int $id, int $userId): JsonResponse
    {
        $report = $this->service->find($id);
        if ($report === null) {
            return response()->json(['message' => 'Không tìm thấy báo cáo.'], 404);
        }

        if (! $this->service->canView($report, $request->user())) {
            return response()->json(['message' => 'Bạn không có quyền xem báo cáo này.'], 403);
        }

        return response()->json($this->service->presentEmployeeDetail($report, $userId));
    }

    public function update(UpdateReportRequest $request, int $id): JsonResponse
    {
        $report = $this->manageableOrFail($request, $id);
        if ($report instanceof JsonResponse) {
            return $report;
        }

        $updated = $this->service->update($report, $request->user(), $request->validated());

        $this->activityLogs->record(
            'report.update',
            'Cập nhật báo cáo "'.$updated->title.'"',
            $request->user(),
            'report',
            (int) $updated->id,
            ['department_id' => $updated->department_id],
        );

        return response()->json(['report' => $this->service->presentDetail($updated)]);
    }

    public function save(Request $request, int $id): JsonResponse
    {
        $report = $this->manageableOrFail($request, $id);
        if ($report instanceof JsonResponse) {
            return $report;
        }

        $saved = $this->service->save($report, $request->user());

        $this->activityLogs->record(
            'report.save',
            'Lưu báo cáo "'.$saved->title.'"',
            $request->user(),
            'report',
            (int) $saved->id,
            ['department_id' => $saved->department_id],
        );

        return response()->json(['report' => $this->service->presentDetail($saved)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $report = $this->manageableOrFail($request, $id);
        if ($report instanceof JsonResponse) {
            return $report;
        }

        $title = $report->title;
        $departmentId = $report->department_id;
        $this->service->delete($report);

        $this->activityLogs->record(
            'report.delete',
            'Xoá báo cáo "'.$title.'"',
            $request->user(),
            'report',
            $id,
            ['department_id' => $departmentId],
        );

        return response()->json(['deleted' => true]);
    }

    private function manageableOrFail(Request $request, int $id): Report|JsonResponse
    {
        $report = $this->service->find($id);
        if ($report === null) {
            return response()->json(['message' => 'Không tìm thấy báo cáo.'], 404);
        }

        if (! $this->service->canManage($report, $request->user())) {
            return response()->json(['message' => 'Bạn không có quyền sửa báo cáo này.'], 403);
        }

        return $report;
    }

    private function departmentIdOrFail(Request $request): int|JsonResponse
    {
        $departmentId = $request->user()?->department_id;

        return $departmentId
            ? (int) $departmentId
            : response()->json(['message' => 'Tài khoản chưa gắn với phòng ban nào.'], 422);
    }
}
