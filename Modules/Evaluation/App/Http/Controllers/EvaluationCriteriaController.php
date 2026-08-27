<?php

namespace Modules\Evaluation\App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Evaluation\App\Http\Requests\ConfirmImportEvaluationCriteriaRequest;
use Modules\Evaluation\App\Http\Requests\ExportEvaluationCriteriaRequest;
use Modules\Evaluation\App\Http\Requests\ImportEvaluationCriteriaRequest;
use Modules\Evaluation\App\Http\Requests\StoreEvaluationCriteriaRequest;
use Modules\Evaluation\App\Http\Requests\UpdateEvaluationCriteriaRequest;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Services\EvaluationCriteriaService;
use Modules\Identity\App\Models\ActivityLog;
use Modules\Identity\App\Services\ActivityLogService;
use Modules\Identity\App\Services\PermissionService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Manager JSON (middleware web + session, bọc qua EvaluationServiceProvider):
 *   GET    /api/evaluation/criteria                — list tiêu chí phòng ban user
 *   GET    /api/evaluation/criteria?department_id= — list cho superadmin (workspace_config.view_all)
 *   GET    /api/evaluation/criteria/history        — lịch sử tạo/sửa/xoá tiêu chí phòng ban
 *   GET    /api/evaluation/criteria/export          — xuất Excel theo bộ lọc hiện tại
 *   GET    /api/evaluation/criteria/export-pdf      — xuất PDF theo bộ lọc hiện tại
 *   POST   /api/evaluation/criteria/import/preview  — đọc + xem trước file Excel, KHÔNG ghi DB (evaluation.manage_department)
 *   POST   /api/evaluation/criteria/import/confirm  — xác nhận nhập, ghi DB từ dữ liệu đã preview (evaluation.manage_department)
 *   POST   /api/evaluation/criteria                — tạo tiêu chí mới
 *   PUT    /api/evaluation/criteria/{id}           — cập nhật tiêu chí
 *   DELETE /api/evaluation/criteria/{id}           — xoá tiêu chí
 *   PATCH  /api/evaluation/criteria/{id}/toggle              — bật/tắt is_active
 *   PATCH  /api/evaluation/criteria/{id}/toggle-evaluation   — bật/tắt use_in_evaluation
 *
 * department_id luôn lấy từ user (manager route) — trưởng phòng chỉ xem/sửa PB mình.
 * Ngoại lệ: GET với ?department_id có thể dùng cho superadmin (kiểm tra workspace_config.view_all).
 */
class EvaluationCriteriaController extends Controller
{
    public function __construct(
        private readonly EvaluationCriteriaService $service,
        private readonly PermissionService $permissions,
        private readonly ActivityLogService $activityLogs,
    ) {}

    private function departmentIdOrFail(Request $request): int|JsonResponse
    {
        $departmentId = $request->user()?->department_id;

        return $departmentId
            ? (int) $departmentId
            : response()->json(['message' => 'Tài khoản chưa gắn với phòng ban nào.'], 422);
    }

    public function index(Request $request): JsonResponse
    {
        $departmentId = $this->resolveDepartmentId($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        return response()->json([
            'criteria' => $this->service->listForDepartment($departmentId),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $departmentId = $this->resolveDepartmentId($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        $criterionIds = $this->service->idsForDepartment($departmentId);
        $logs = $this->activityLogs->recentForSubject(
            'evaluation_criteria',
            $criterionIds,
            ['department_id' => $departmentId],
            80,
        );

        return response()->json([
            'logs' => $logs
                ->map(fn (ActivityLog $log) => $this->presentHistoryLog($log, $criterionIds))
                ->values()
                ->all(),
        ]);
    }

    /**
     * Xuất Excel theo bộ lọc hiện tại. Trưởng phòng ban (evaluation.manage_department)
     * xuất được toàn bộ tiêu chí phòng ban mình; thành viên thường chỉ xuất được đúng
     * dữ liệu họ đang xem ở trang /manager/evaluation (không cần quyền quản lý).
     */
    public function export(ExportEvaluationCriteriaRequest $request): BinaryFileResponse|JsonResponse
    {
        $departmentId = $this->resolveDepartmentId($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        return $this->service->export($departmentId, $request->filters(), $request->user());
    }

    /** Xuất PDF theo bộ lọc hiện tại — cùng điều kiện quyền với export() (hành động đọc). */
    public function exportPdf(ExportEvaluationCriteriaRequest $request)
    {
        $departmentId = $this->resolveDepartmentId($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        return $this->service->exportPdf($departmentId, $request->filters(), $request->user());
    }

    /** Đọc + xem trước file Excel — KHÔNG ghi DB, chỉ trả bảng kết quả validate cho từng dòng. */
    public function importPreview(ImportEvaluationCriteriaRequest $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền nhập tiêu chí đánh giá.'], 403);
        }

        $result = $this->service->previewImport($departmentId, $request->file('file'));

        return response()->json($result);
    }

    /** Xác nhận nhập — nhận JSON các dòng đã preview (không nhận file), ghi DB thật. */
    public function importConfirm(ConfirmImportEvaluationCriteriaRequest $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền nhập tiêu chí đánh giá.'], 403);
        }

        $result = $this->service->confirmImport(
            $departmentId,
            (int) $request->user()->id,
            $request->validated()['rows'],
        );

        foreach ($result['created'] as $criterion) {
            $this->activityLogs->record(
                'evaluation_criteria.create',
                'Tạo tiêu chí đánh giá "'.$criterion['name'].'" (nhập từ Excel)',
                $request->user(),
                'evaluation_criteria',
                (int) $criterion['id'],
                ['department_id' => $departmentId, 'name' => $criterion['name']],
            );
        }

        return response()->json($result);
    }

    public function store(StoreEvaluationCriteriaRequest $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền tạo tiêu chí đánh giá.'], 403);
        }

        $criterion = $this->service->create(
            $departmentId,
            (int) $request->user()->id,
            $request->validated(),
        );

        $this->recordCriterionActivity(
            'evaluation_criteria.create',
            'Tạo tiêu chí đánh giá "'.$criterion->name.'"',
            $request->user(),
            $criterion,
        );

        return response()->json(['criterion' => $this->service->present($criterion)], 201);
    }

    public function update(UpdateEvaluationCriteriaRequest $request, int $id): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền cập nhật tiêu chí đánh giá.'], 403);
        }

        $criterion = $this->service->findByDepartmentOrFail($id, $departmentId);
        if ($criterion instanceof JsonResponse) {
            return $criterion;
        }

        $updated = $this->service->update($criterion, $request->validated(), (int) $request->user()->id);

        $this->recordCriterionActivity(
            'evaluation_criteria.update',
            'Cập nhật tiêu chí đánh giá "'.$updated->name.'"',
            $request->user(),
            $updated,
        );

        return response()->json(['criterion' => $this->service->present($updated)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền xoá tiêu chí đánh giá.'], 403);
        }

        $criterion = $this->service->findByDepartmentOrFail($id, $departmentId);
        if ($criterion instanceof JsonResponse) {
            return $criterion;
        }

        $name = $criterion->name;
        $this->recordCriterionActivity(
            'evaluation_criteria.delete',
            'Xoá tiêu chí đánh giá "'.$name.'"',
            $request->user(),
            $criterion,
        );
        $this->service->delete($criterion);

        return response()->json(['message' => 'Đã xoá tiêu chí đánh giá.']);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền cập nhật tiêu chí đánh giá.'], 403);
        }

        $criterion = $this->service->findByDepartmentOrFail($id, $departmentId);
        if ($criterion instanceof JsonResponse) {
            return $criterion;
        }

        $updated = $this->service->toggleActive($criterion, (int) $request->user()->id);

        $this->recordCriterionActivity(
            'evaluation_criteria.update',
            ($updated->is_active ? 'Hiện' : 'Ẩn').' tiêu chí đánh giá "'.$updated->name.'"',
            $request->user(),
            $updated,
        );

        return response()->json(['criterion' => $this->service->present($updated)]);
    }

    public function toggleUseInEvaluation(Request $request, int $id): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền cập nhật tiêu chí đánh giá.'], 403);
        }

        $criterion = $this->service->findByDepartmentOrFail($id, $departmentId);
        if ($criterion instanceof JsonResponse) {
            return $criterion;
        }

        $updated = $this->service->toggleUseInEvaluation($criterion, (int) $request->user()->id);

        $this->recordCriterionActivity(
            'evaluation_criteria.update',
            ($updated->use_in_evaluation ? 'Bật' : 'Tắt').' ĐGNL cho tiêu chí "'.$updated->name.'"',
            $request->user(),
            $updated,
        );

        return response()->json(['criterion' => $this->service->present($updated)]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền sắp xếp tiêu chí đánh giá.'], 403);
        }

        $ids = $request->input('ids', []);
        if (! is_array($ids)) {
            return response()->json(['message' => 'Danh sách IDs không hợp lệ.'], 422);
        }

        $this->service->reorder($departmentId, array_map('intval', $ids));

        return response()->json(['message' => 'Đã cập nhật thứ tự tiêu chí.']);
    }

    private function resolveDepartmentId(Request $request): int|JsonResponse
    {
        $queryDeptId = $request->query('department_id');

        if ($queryDeptId !== null) {
            if (! $this->permissions->allows($request->user(), 'workspace_config.view_all')) {
                return response()->json(['message' => 'Không có quyền xem tiêu chí phòng ban khác.'], 403);
            }

            return (int) $queryDeptId;
        }

        return $this->departmentIdOrFail($request);
    }

    private function recordCriterionActivity(
        string $action,
        string $description,
        ?User $actor,
        EvaluationCriteria $criterion,
    ): void {
        $this->activityLogs->record(
            $action,
            $description,
            $actor,
            'evaluation_criteria',
            (int) $criterion->id,
            [
                'department_id' => (int) $criterion->department_id,
                'name' => $criterion->name,
            ],
        );
    }

    /**
     * @param  list<int>  $existingIds
     * @return array<string, mixed>
     */
    private function presentHistoryLog(ActivityLog $log, array $existingIds): array
    {
        $name = trim((string) ($log->properties['name'] ?? ''));
        if ($name === '') {
            $name = $this->nameFromDescription((string) $log->description);
        }

        $verb = match ($log->action) {
            'evaluation_criteria.create' => 'đã tạo',
            'evaluation_criteria.delete' => 'đã xoá',
            default => 'đã sửa',
        };

        $subjectId = $log->subject_id ? (int) $log->subject_id : null;
        $detail = $subjectId
            ? 'ID: '.$subjectId.($name !== '' ? ' - '.$name : '')
            : (string) $log->description;

        $actor = $log->actor;
        $department = $actor?->department;

        return [
            'id' => $log->id,
            'action' => $log->action,
            'verb' => $verb,
            'actor_name' => $actor?->name ?: ($log->actor_name ?: 'Hệ thống'),
            'actor' => [
                'id' => $actor?->id ?? $log->actor_id,
                'name' => $actor?->name ?: ($log->actor_name ?: 'Hệ thống'),
                'email' => $actor?->email ?? $log->actor_email,
                'avatar_url' => $actor?->avatar_url,
                'department' => $department ? [
                    'id' => $department->id,
                    'name' => $department->name,
                ] : null,
            ],
            'subject_id' => $subjectId,
            'detail' => $detail,
            'created_at' => $log->created_at?->toIso8601String(),
            'can_open' => $subjectId !== null
                && $log->action !== 'evaluation_criteria.delete'
                && in_array($subjectId, $existingIds, true),
        ];
    }

    private function nameFromDescription(string $description): string
    {
        if (preg_match('/"([^"]+)"/u', $description, $match) === 1) {
            return $match[1];
        }

        return '';
    }
}
