<?php

namespace Modules\Project\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Project\App\Enums\TaskEnums;
use Modules\Project\App\Http\Requests\StoreTaskRequest;
use Modules\Project\App\Http\Requests\UpdateTaskRequest;
use Modules\Project\App\Services\ProjectService;
use Modules\Project\App\Services\TaskService;

/**
 * Controller mỏng: chỉ nhận request, gọi Service, trả response. Không chứa
 * business logic hay truy vấn DB — xem TaskService/TaskRepository.
 */
class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $service,
        private readonly ProjectService $projects,
    ) {}

    /** GET /api/project/tasks — xuyên project, cho trang "Tất cả công việc". */
    public function index(Request $request)
    {
        $filters = $request->only(['project_id', 'assignee_id', 'status', 'type', 'date_from', 'date_to', 'q', 'tab']);
        $perPage = (int) $request->input('per_page', 20);
        $page = (int) $request->input('page', 1);
        $viewer = $request->user();

        $paginated = $this->service->paginate($filters, $perPage, $page, $viewer);

        return response()->json([
            'tasks' => collect($paginated->items())->map(fn ($t) => $this->service->present($t))->values(),
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

    /** GET /api/project/{project}/tasks — cây WBS trong 1 project. */
    public function treeByProject(Request $request, int $project)
    {
        $model = $this->projects->find($project);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy dự án.'], 404);
        }

        return response()->json(['tasks' => $this->service->treeForProject($model)]);
    }

    /** GET /api/project/tasks/{task} */
    public function show(int $task)
    {
        $model = $this->service->find($task);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy công việc.'], 404);
        }

        return response()->json(['task' => $this->service->present($model)]);
    }

    /** POST /api/project/{project}/tasks — tạo Task luôn trong ngữ cảnh 1 project. */
    public function store(StoreTaskRequest $request, int $project)
    {
        $model = $this->projects->find($project);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy dự án.'], 404);
        }

        $result = $this->service->create($model, $request->validated(), $request->user());

        if (is_array($result)) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['task' => $this->service->present($result)], 201);
    }

    /** PUT /api/project/tasks/{task} */
    public function update(UpdateTaskRequest $request, int $task)
    {
        $model = $this->service->find($task);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy công việc.'], 404);
        }

        $result = $this->service->update($model, $request->validated(), $request->user());

        if (is_array($result)) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['task' => $this->service->present($result)]);
    }

    /** DELETE /api/project/tasks/{task} */
    public function destroy(int $task)
    {
        $model = $this->service->find($task);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy công việc.'], 404);
        }

        $result = $this->service->delete($model);

        if (is_array($result)) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['message' => 'Đã xoá công việc.']);
    }

    /** GET /api/project/tasks/options */
    public function options()
    {
        return response()->json(TaskEnums::options());
    }
}
