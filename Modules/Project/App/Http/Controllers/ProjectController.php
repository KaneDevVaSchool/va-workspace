<?php

namespace Modules\Project\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Project\App\Enums\ProjectEnums;
use Modules\Project\App\Exceptions\ProjectOwnerDepartmentMissing;
use Modules\Project\App\Http\Requests\StoreProjectLabelRequest;
use Modules\Project\App\Http\Requests\StoreProjectRequest;
use Modules\Project\App\Http\Requests\UpdateProjectRequest;
use Modules\Project\App\Http\Requests\UpdateProjectSettingsRequest;
use Modules\Project\App\Http\Requests\UploadProjectAttachmentRequest;
use Modules\Project\App\Http\Requests\UploadProjectAvatarRequest;
use Modules\Project\App\Repositories\Contracts\ProjectRepositoryInterface;
use Modules\Project\App\Services\ProjectService;

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
        $filters['label_ids'] = $request->input('label_ids', []);
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

    public function assignableUsers(Request $request)
    {
        $users = $this->service->assignableUsers($request->user());

        return response()->json([
            'users' => $users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar_url' => $u->avatar_url,
            ])->values(),
        ]);
    }

    public function options()
    {
        return response()->json(ProjectEnums::options());
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
