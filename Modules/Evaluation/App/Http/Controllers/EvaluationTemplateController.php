<?php

namespace Modules\Evaluation\App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Evaluation\App\Http\Requests\ExportEvaluationTemplateRequest;
use Modules\Evaluation\App\Http\Requests\StoreEvaluationTemplateRequest;
use Modules\Evaluation\App\Http\Requests\UpdateEvaluationTemplateRequest;
use Modules\Evaluation\App\Models\EvaluationTemplate;
use Modules\Evaluation\App\Services\EvaluationTemplateService;
use Modules\Identity\App\Services\ActivityLogService;
use Modules\Identity\App\Services\PermissionService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Manager JSON (middleware web + session, bọc qua EvaluationServiceProvider):
 *   GET    /api/evaluation/templates                — mẫu của phòng ban user + mọi mẫu is_global
 *   GET    /api/evaluation/templates?department_id= — toàn bộ mẫu, chỉ superadmin (workspace_config.view_all)
 *   GET    /api/evaluation/templates/{id}
 *   POST   /api/evaluation/templates                — tạo mẫu mới (evaluation.manage_department)
 *   PUT    /api/evaluation/templates/{id}
 *   DELETE /api/evaluation/templates/{id}
 *   PATCH  /api/evaluation/templates/{id}/toggle          — bật/tắt is_active
 *   PATCH  /api/evaluation/templates/{id}/toggle-global   — bật/tắt is_global (evaluation.manage_global_template)
 *   POST   /api/evaluation/templates/{id}/duplicate       — nhân bản mẫu
 *   GET    /api/evaluation/templates/global-criteria-pool — tiêu chí active MỌI phòng ban, dùng build mẫu global
 *                                                            (evaluation.manage_global_template)
 *   GET    /api/evaluation/templates/export               — xuất Excel theo bộ lọc hiện tại (PR6, CHỈ xuất — không nhập lại)
 *
 * department_id luôn lấy từ user (manager route), trừ khi có ?department_id
 * kèm quyền workspace_config.view_all (superadmin xem tổng hợp, không sửa).
 * Xem plans/2026-08-26-mau-danh-gia.md.
 */
class EvaluationTemplateController extends Controller
{
    public function __construct(
        private readonly EvaluationTemplateService $service,
        private readonly PermissionService $permissions,
        private readonly ActivityLogService $activityLogs,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $queryDeptId = $request->query('department_id');

        if ($queryDeptId !== null) {
            if (! $this->permissions->allows($request->user(), 'workspace_config.view_all')) {
                return response()->json(['message' => 'Không có quyền xem mẫu đánh giá phòng ban khác.'], 403);
            }

            return response()->json(['templates' => $this->service->listAll()]);
        }

        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        return response()->json(['templates' => $this->service->listVisibleForDepartment($departmentId)]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        $template = $this->service->findVisibleOrFail($id, $departmentId);
        if ($template instanceof JsonResponse) {
            return $template;
        }

        return response()->json(['template' => $this->service->present($template)]);
    }

    public function store(StoreEvaluationTemplateRequest $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền tạo mẫu đánh giá.'], 403);
        }

        $result = $this->service->create($departmentId, (int) $request->user()->id, $request->validated());
        if (is_array($result) && isset($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }

        $this->recordActivity('evaluation_template.create', 'Tạo mẫu đánh giá "'.$result->name.'"', $request->user(), $result);

        return response()->json(['template' => $this->service->present($result)], 201);
    }

    public function update(UpdateEvaluationTemplateRequest $request, int $id): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền cập nhật mẫu đánh giá.'], 403);
        }

        $template = $this->service->findVisibleOrFail($id, $departmentId);
        if ($template instanceof JsonResponse) {
            return $template;
        }

        if ((int) $template->department_id !== $departmentId) {
            return response()->json(['message' => 'Chỉ phòng ban tạo ra mẫu mới được sửa mẫu này.'], 403);
        }

        $result = $this->service->update($template, $request->validated(), (int) $request->user()->id);
        if (is_array($result) && isset($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }

        $this->recordActivity('evaluation_template.update', 'Cập nhật mẫu đánh giá "'.$result->name.'"', $request->user(), $result);

        return response()->json(['template' => $this->service->present($result)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền xoá mẫu đánh giá.'], 403);
        }

        $template = $this->service->findVisibleOrFail($id, $departmentId);
        if ($template instanceof JsonResponse) {
            return $template;
        }

        if ((int) $template->department_id !== $departmentId) {
            return response()->json(['message' => 'Chỉ phòng ban tạo ra mẫu mới được xoá mẫu này.'], 403);
        }

        $name = $template->name;
        $this->recordActivity('evaluation_template.delete', 'Xoá mẫu đánh giá "'.$name.'"', $request->user(), $template);
        $this->service->delete($template);

        return response()->json(['message' => 'Đã xoá mẫu đánh giá.']);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền cập nhật mẫu đánh giá.'], 403);
        }

        $template = $this->service->findVisibleOrFail($id, $departmentId);
        if ($template instanceof JsonResponse) {
            return $template;
        }

        $updated = $this->service->toggleActive($template, (int) $request->user()->id);

        $this->recordActivity(
            'evaluation_template.update',
            ($updated->is_active ? 'Bật' : 'Tắt').' mẫu đánh giá "'.$updated->name.'"',
            $request->user(),
            $updated,
        );

        return response()->json(['template' => $this->service->present($updated)]);
    }

    /** Bật/tắt dùng chung toàn hệ thống — reserved cho department_director trở lên. */
    public function toggleGlobal(Request $request, int $id): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_global_template', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền đánh dấu mẫu dùng chung toàn hệ thống.'], 403);
        }

        $template = $this->service->findVisibleOrFail($id, $departmentId);
        if ($template instanceof JsonResponse) {
            return $template;
        }

        if ((int) $template->department_id !== $departmentId) {
            return response()->json(['message' => 'Chỉ phòng ban tạo ra mẫu mới được đổi trạng thái dùng chung.'], 403);
        }

        $updated = $this->service->toggleGlobal($template, (int) $request->user()->id);

        $this->recordActivity(
            'evaluation_template.update',
            ($updated->is_global ? 'Đánh dấu dùng chung toàn hệ thống' : 'Bỏ dùng chung toàn hệ thống').' cho mẫu đánh giá "'.$updated->name.'"',
            $request->user(),
            $updated,
        );

        return response()->json(['template' => $this->service->present($updated)]);
    }

    public function duplicate(Request $request, int $id): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền nhân bản mẫu đánh giá.'], 403);
        }

        $template = $this->service->findVisibleOrFail($id, $departmentId);
        if ($template instanceof JsonResponse) {
            return $template;
        }

        $copy = $this->service->duplicate($template, $departmentId, (int) $request->user()->id);

        $this->recordActivity('evaluation_template.create', 'Nhân bản mẫu đánh giá "'.$copy->name.'"', $request->user(), $copy);

        return response()->json(['template' => $this->service->present($copy)], 201);
    }

    /**
     * Tiêu chí active của MỌI phòng ban, kèm tên phòng ban nguồn — chỉ cho
     * người có evaluation.manage_global_template (đang build/sửa mẫu global).
     */
    public function globalCriteriaPool(Request $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'evaluation.manage_global_template', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền xem tiêu chí phòng ban khác.'], 403);
        }

        return response()->json(['criteria' => $this->service->listCriteriaAcrossDepartments()]);
    }

    /**
     * Xuất Excel theo bộ lọc hiện tại (PR6) — CHỈ xuất, không có chiều nhập
     * lại cho Mẫu đánh giá (khác tiêu chí đánh giá). Ai xem được trang đều
     * xuất được đúng dữ liệu đang xem, không cần quyền quản lý riêng.
     */
    public function export(ExportEvaluationTemplateRequest $request): BinaryFileResponse|JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        return $this->service->export($departmentId, $request->filters(), $request->user());
    }

    private function departmentIdOrFail(Request $request): int|JsonResponse
    {
        $departmentId = $request->user()?->department_id;

        return $departmentId
            ? (int) $departmentId
            : response()->json(['message' => 'Tài khoản chưa gắn với phòng ban nào.'], 422);
    }

    private function recordActivity(string $action, string $description, ?User $actor, EvaluationTemplate $template): void
    {
        $this->activityLogs->record(
            $action,
            $description,
            $actor,
            'evaluation_template',
            (int) $template->id,
            [
                'department_id' => (int) $template->department_id,
                'name'          => $template->name,
            ],
        );
    }
}
