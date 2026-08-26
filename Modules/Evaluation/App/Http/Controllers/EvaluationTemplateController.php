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
 *                                                      (superadmin: toàn bộ mẫu)
 *   GET    /api/evaluation/templates?department_id= — toàn bộ mẫu, chỉ superadmin (workspace_config.view_all)
 *   GET    /api/evaluation/templates/{id}
 *   POST   /api/evaluation/templates                — tạo mẫu mới (evaluation.manage_department,
 *                                                      hoặc evaluation.manage_global_template khi không có phòng ban)
 *   PUT    /api/evaluation/templates/{id}
 *   DELETE /api/evaluation/templates/{id}
 *   PATCH  /api/evaluation/templates/{id}/toggle          — bật/tắt is_active
 *   PATCH  /api/evaluation/templates/{id}/toggle-global   — bật/tắt is_global (evaluation.manage_global_template)
 *   POST   /api/evaluation/templates/{id}/duplicate       — nhân bản mẫu
 *   GET    /api/evaluation/templates/global-criteria-pool — tiêu chí active MỌI phòng ban, dùng build mẫu global
 *                                                            (evaluation.manage_global_template)
 *   GET    /api/evaluation/templates/export               — xuất Excel theo bộ lọc hiện tại (PR6, CHỈ xuất — không nhập lại)
 *
 * Superadmin (workspace_config.view_all) xem mọi mẫu, tạo/sửa mẫu dùng chung
 * (is_global, có thể không gắn phòng ban). Trưởng phòng chỉ sửa mẫu của phòng mình.
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
        $user = $request->user();
        $queryDeptId = $request->query('department_id');

        if ($queryDeptId !== null || $this->canViewAll($user)) {
            if (! $this->canViewAll($user)) {
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
        $template = $this->findAccessibleOrFail($request, $id);
        if ($template instanceof JsonResponse) {
            return $template;
        }

        return response()->json(['template' => $this->service->present($template)]);
    }

    public function store(StoreEvaluationTemplateRequest $request): JsonResponse
    {
        $user = $request->user();
        $departmentId = $this->departmentIdOf($user);
        $wantGlobal = $request->boolean('is_global');

        if ($departmentId === null) {
            if (! $this->permissions->allows($user, 'evaluation.manage_global_template')) {
                return response()->json(['message' => 'Tài khoản chưa gắn với phòng ban nào.'], 422);
            }
            $wantGlobal = true;
        } elseif ($wantGlobal) {
            if (! $this->permissions->allows($user, 'evaluation.manage_global_template', 'department', $departmentId)) {
                return response()->json(['message' => 'Bạn không có quyền tạo mẫu dùng chung toàn hệ thống.'], 403);
            }
        } elseif (! $this->permissions->allows($user, 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền tạo mẫu đánh giá.'], 403);
        }

        $data = $request->validated();
        $data['is_global'] = $wantGlobal;

        $result = $this->service->create($departmentId, (int) $user->id, $data);
        if (is_array($result) && isset($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }

        $this->recordActivity('evaluation_template.create', 'Tạo mẫu đánh giá "'.$result->name.'"', $user, $result);

        return response()->json(['template' => $this->service->present($result)], 201);
    }

    public function update(UpdateEvaluationTemplateRequest $request, int $id): JsonResponse
    {
        $template = $this->findAccessibleOrFail($request, $id);
        if ($template instanceof JsonResponse) {
            return $template;
        }

        if (! $this->canMutate($request->user(), $template)) {
            return response()->json(['message' => 'Bạn không có quyền cập nhật mẫu đánh giá.'], 403);
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
        $template = $this->findAccessibleOrFail($request, $id);
        if ($template instanceof JsonResponse) {
            return $template;
        }

        if (! $this->canMutate($request->user(), $template)) {
            return response()->json(['message' => 'Bạn không có quyền xoá mẫu đánh giá.'], 403);
        }

        $name = $template->name;
        $this->recordActivity('evaluation_template.delete', 'Xoá mẫu đánh giá "'.$name.'"', $request->user(), $template);
        $this->service->delete($template);

        return response()->json(['message' => 'Đã xoá mẫu đánh giá.']);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $template = $this->findAccessibleOrFail($request, $id);
        if ($template instanceof JsonResponse) {
            return $template;
        }

        if (! $this->canMutate($request->user(), $template)) {
            return response()->json(['message' => 'Bạn không có quyền cập nhật mẫu đánh giá.'], 403);
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

    /** Bật/tắt dùng chung toàn hệ thống — department_director trở lên + superadmin. */
    public function toggleGlobal(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $template = $this->findAccessibleOrFail($request, $id);
        if ($template instanceof JsonResponse) {
            return $template;
        }

        $departmentId = $this->departmentIdOf($user);
        if (! $this->permissions->allows($user, 'evaluation.manage_global_template', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền đánh dấu mẫu dùng chung toàn hệ thống.'], 403);
        }

        if (! $this->canMutate($user, $template)) {
            return response()->json(['message' => 'Chỉ người quản lý mẫu mới được đổi trạng thái dùng chung.'], 403);
        }

        if ($template->department_id === null && $template->is_global) {
            return response()->json(['message' => 'Mẫu dùng chung do hệ thống tạo không thể bỏ dùng chung.'], 422);
        }

        $updated = $this->service->toggleGlobal($template, (int) $user->id);

        $this->recordActivity(
            'evaluation_template.update',
            ($updated->is_global ? 'Đánh dấu dùng chung toàn hệ thống' : 'Bỏ dùng chung toàn hệ thống').' cho mẫu đánh giá "'.$updated->name.'"',
            $user,
            $updated,
        );

        return response()->json(['template' => $this->service->present($updated)]);
    }

    public function duplicate(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $template = $this->findAccessibleOrFail($request, $id);
        if ($template instanceof JsonResponse) {
            return $template;
        }

        $departmentId = $this->departmentIdOf($user);

        if ($departmentId === null) {
            if (! $this->permissions->allows($user, 'evaluation.manage_global_template')) {
                return response()->json(['message' => 'Bạn không có quyền nhân bản mẫu đánh giá.'], 403);
            }
        } elseif (! $this->permissions->allows($user, 'evaluation.manage_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền nhân bản mẫu đánh giá.'], 403);
        }

        $copy = $this->service->duplicate($template, $departmentId, (int) $user->id);

        $this->recordActivity('evaluation_template.create', 'Nhân bản mẫu đánh giá "'.$copy->name.'"', $user, $copy);

        return response()->json(['template' => $this->service->present($copy)], 201);
    }

    /**
     * Tiêu chí active của MỌI phòng ban, kèm tên phòng ban nguồn — chỉ cho
     * người có evaluation.manage_global_template (đang build/sửa mẫu global).
     */
    public function globalCriteriaPool(Request $request): JsonResponse
    {
        $user = $request->user();
        $departmentId = $this->departmentIdOf($user);

        if (! $this->permissions->allows($user, 'evaluation.manage_global_template', 'department', $departmentId)) {
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
        $user = $request->user();

        if ($this->canViewAll($user)) {
            return $this->service->export(null, $request->filters(), $user);
        }

        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        return $this->service->export($departmentId, $request->filters(), $user);
    }

    private function canViewAll(?User $user): bool
    {
        return $user !== null && $this->permissions->allows($user, 'workspace_config.view_all');
    }

    private function departmentIdOf(?User $user): ?int
    {
        return $user?->department_id ? (int) $user->department_id : null;
    }

    private function departmentIdOrFail(Request $request): int|JsonResponse
    {
        $departmentId = $this->departmentIdOf($request->user());

        return $departmentId
            ? $departmentId
            : response()->json(['message' => 'Tài khoản chưa gắn với phòng ban nào.'], 422);
    }

    private function findAccessibleOrFail(Request $request, int $id): EvaluationTemplate|JsonResponse
    {
        if ($this->canViewAll($request->user())) {
            return $this->service->findOrFail($id);
        }

        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        return $this->service->findVisibleOrFail($id, $departmentId);
    }

    /**
     * Superadmin sửa được mẫu dùng chung. Trưởng phòng chỉ sửa mẫu của phòng mình.
     */
    private function canMutate(?User $user, EvaluationTemplate $template): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->canViewAll($user)) {
            return $template->is_global;
        }

        $departmentId = $this->departmentIdOf($user);
        if ($departmentId === null || (int) $template->department_id !== $departmentId) {
            return false;
        }

        return $this->permissions->allows($user, 'evaluation.manage_department', 'department', $departmentId);
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
                'department_id' => $template->department_id !== null ? (int) $template->department_id : null,
                'name'          => $template->name,
            ],
        );
    }
}
