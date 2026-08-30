<?php

namespace Modules\Project\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Project\App\Enums\ProjectEnums;
use Modules\Project\App\Exceptions\ProjectOwnerDepartmentMissing;
use Modules\Project\App\Http\Requests\ConfirmImportProjectRequest;
use Modules\Project\App\Http\Requests\ExportProjectRequest;
use Modules\Project\App\Http\Requests\ImportProjectRequest;
use Modules\Project\App\Http\Requests\ResolveImportProjectRowRequest;
use Modules\Project\App\Http\Requests\StoreProjectLabelRequest;
use Modules\Project\App\Http\Requests\StoreProjectQuickItemRequest;
use Modules\Project\App\Http\Requests\StoreProjectRequest;
use Modules\Project\App\Http\Requests\StoreProjectTypeRequest;
use Modules\Project\App\Http\Requests\UpdateProjectRequest;
use Modules\Project\App\Http\Requests\UpdateProjectSettingsRequest;
use Modules\Project\App\Http\Requests\UploadProjectAttachmentRequest;
use Modules\Project\App\Http\Requests\UploadProjectAvatarRequest;
use Modules\Project\App\Repositories\Contracts\ProjectRepositoryInterface;
use Modules\Project\App\Services\ProjectService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Controller mỏng: chỉ nhận request, gọi Service, trả response. Không chứa
 * business logic hay truy vấn DB — xem ProjectService/ProjectRepository.
 */
class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $service,
        private readonly ProjectRepositoryInterface $projects,
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only(['q', 'type', 'status', 'importance', 'lead_user_id', 'tab']);
        $labelIds = $request->input('label_ids', []);
        if (! is_array($labelIds)) {
            $labelIds = $labelIds === null || $labelIds === '' ? [] : [$labelIds];
        }
        $filters['label_ids'] = $labelIds;
        $perPage = (int) $request->input('per_page', 20);
        $page = (int) $request->input('page', 1);
        $viewer = $request->user();

        $paginated = $this->service->paginate($filters, $perPage, $page, $viewer);

        return response()->json([
            'projects' => collect($paginated->items())->map(fn ($p) => $this->service->present($p, $viewer))->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem() ?? 0,
                'to' => $paginated->lastItem() ?? 0,
                'per_page' => $paginated->perPage(),
            ],
            'tab_counts' => $this->service->tabCounts($viewer),
        ]);
    }

    public function show(Request $request, int $project)
    {
        $model = $this->service->find($project);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy dự án.'], 404);
        }

        return response()->json(['project' => $this->service->present($model, $request->user())]);
    }

    public function store(StoreProjectRequest $request)
    {
        try {
            $result = $this->service->create($request->validated(), $request->user());
        } catch (ProjectOwnerDepartmentMissing $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (is_array($result)) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['project' => $this->service->present($result, $request->user())], 201);
    }

    /** Xuất Excel theo bộ lọc hiện tại. Ai xem được trang đều xuất được. */
    public function export(ExportProjectRequest $request): BinaryFileResponse
    {
        return $this->service->export($request->filters(), $request->columns(), $request->user());
    }

    /** Đọc + xem trước file Excel — KHÔNG ghi DB. */
    public function importPreview(ImportProjectRequest $request): JsonResponse
    {
        $result = $this->service->previewImport($request->file('file'), $request->user());

        return response()->json($result);
    }

    /** Sửa lỗi 1 dòng tại chỗ trong bảng xem trước — re-resolve, không đọc lại file. */
    public function importResolveRow(ResolveImportProjectRowRequest $request): JsonResponse
    {
        $result = $this->service->resolveImportRow($request->validated(), $request->user());

        return response()->json($result);
    }

    /** Xác nhận nhập — nhận JSON các dòng đã preview (không nhận file), ghi DB thật. */
    public function importConfirm(ConfirmImportProjectRequest $request): JsonResponse
    {
        try {
            $result = $this->service->confirmImport(
                $request->validated()['rows'],
                $request->user(),
            );
        } catch (ProjectOwnerDepartmentMissing $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function update(UpdateProjectRequest $request, int $project)
    {
        $model = $this->service->find($project);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy dự án.'], 404);
        }

        $result = $this->service->update($model, $request->validated(), $request->user());

        if (is_array($result)) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['project' => $this->service->present($result, $request->user())]);
    }

    public function destroy(int $project)
    {
        $model = $this->service->find($project);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy dự án.'], 404);
        }

        $this->service->delete($model);

        return response()->json(['message' => 'Đã xoá dự án.']);
    }

    public function duplicate(Request $request, int $project)
    {
        $model = $this->service->find($project);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy dự án.'], 404);
        }

        if (! $this->service->userCanCreate($request->user())) {
            return response()->json(['message' => 'Bạn không có quyền tạo dự án.'], 403);
        }

        try {
            $result = $this->service->duplicate($model, $request->user());
        } catch (ProjectOwnerDepartmentMissing $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (is_array($result)) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['project' => $this->service->present($result, $request->user())], 201);
    }

    public function quickItemsIndex(Request $request, int $project)
    {
        $model = $this->service->find($project);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy dự án.'], 404);
        }

        $kind = $request->query('kind');
        $kind = is_string($kind) && $kind !== '' ? $kind : null;

        return response()->json($this->service->listQuickItems($model, $kind));
    }

    public function quickItemsStore(StoreProjectQuickItemRequest $request, int $project)
    {
        $model = $this->service->find($project);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy dự án.'], 404);
        }

        $result = $this->service->createQuickItems($model, $request->validated(), $request->user());

        return response()->json($result, 201);
    }

    public function uploadAttachment(UploadProjectAttachmentRequest $request, int $project)
    {
        $model = $this->service->find($project);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy dự án.'], 404);
        }

        $result = $this->service->uploadAttachment(
            $model,
            $request->file('file'),
            $request->input('url'),
            $request->user()->id,
        );

        if (is_array($result)) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['attachment' => $this->service->presentAttachment($result)], 201);
    }

    public function destroyAttachment(int $project, int $attachment)
    {
        $model = $this->service->find($project);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy dự án.'], 404);
        }

        $error = $this->service->destroyAttachment($model, $attachment);
        if ($error !== null) {
            return response()->json(['message' => $error['error']], 404);
        }

        return response()->json(['message' => 'Đã xoá tệp đính kèm.']);
    }

    public function uploadAvatar(UploadProjectAvatarRequest $request, int $project)
    {
        $model = $this->service->find($project);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy dự án.'], 404);
        }

        $updated = $this->service->updateAvatar($model, $request->file('avatar'), $request->user()->id);

        return response()->json(['project' => $this->service->present($updated, $request->user())]);
    }

    public function destroyAvatar(Request $request, int $project)
    {
        $model = $this->service->find($project);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy dự án.'], 404);
        }

        $updated = $this->service->deleteAvatar($model, $request->user()->id);

        return response()->json(['project' => $this->service->present($updated, $request->user())]);
    }

    public function assignableUsers(Request $request)
    {
        $unrestricted = $request->boolean('unrestricted');
        $users = $this->service->assignableUsers($request->user(), $unrestricted);

        return response()->json([
            'users' => $users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar_url' => $u->avatar_url,
                'status' => $u->status,
                'department' => $u->department ? [
                    'id' => $u->department->id,
                    'name' => $u->department->name,
                ] : null,
            ])->values(),
        ]);
    }

    public function options(Request $request)
    {
        return response()->json(array_merge(ProjectEnums::options(), [
            'type' => $this->service->allTypes(),
            'can_create' => $this->service->userCanCreate($request->user()),
            'can_choose_owner_department' => $this->service->userCanChooseOwnerDepartment($request->user()),
        ]));
    }

    // ---------- Theo dõi dự án (mục B) ----------

    public function follow(Request $request, int $project)
    {
        $model = $this->service->find($project);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy dự án.'], 404);
        }

        $this->service->follow($model, $request->user());

        return response()->json(['is_following' => true]);
    }

    public function unfollow(Request $request, int $project)
    {
        $model = $this->service->find($project);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy dự án.'], 404);
        }

        $this->service->unfollow($model, $request->user());

        return response()->json(['is_following' => false]);
    }

    // ---------- Nhãn (mục E) ----------

    public function labelsIndex()
    {
        return response()->json(['labels' => $this->service->allLabels()]);
    }

    public function labelsStore(StoreProjectLabelRequest $request)
    {
        $result = $this->service->createLabel(
            $request->validated()['name'],
            $request->validated()['color'],
            $request->user()->id,
        );

        if (is_array($result)) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['label' => ['id' => $result->id, 'name' => $result->name, 'color' => $result->color]], 201);
    }

    // ---------- Loại dự án (mục A) ----------

    public function typesIndex()
    {
        return response()->json(['types' => $this->service->allTypes()]);
    }

    public function typesStore(StoreProjectTypeRequest $request)
    {
        $result = $this->service->createType($request->validated()['name'], $request->user()->id);

        if (is_array($result)) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['type' => ['value' => $result->name, 'label' => $result->name]], 201);
    }

    // ---------- Cài đặt dự án (mục D) ----------

    public function settingsGeneral()
    {
        return response()->json($this->service->presentSettings());
    }

    public function settingsGeneralUpdate(UpdateProjectSettingsRequest $request)
    {
        $this->service->updateSettings($request->validated());

        return response()->json($this->service->presentSettings());
    }

    public function settingsCreatorAllowlist()
    {
        return response()->json(['users' => $this->service->creatorAllowlistUsers()]);
    }

    public function settingsCreatorAllowlistUpdate(Request $request)
    {
        $data = $request->validate([
            'user_ids' => ['present', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $this->service->replaceCreatorAllowlist($data['user_ids']);

        return response()->json(['users' => $this->service->creatorAllowlistUsers()]);
    }
}
