<?php

namespace Modules\Project\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Project\App\Enums\TaskEnums;
use Modules\Project\App\Http\Requests\BulkDelegateTaskRequest;
use Modules\Project\App\Http\Requests\BulkUpdateTaskRequest;
use Modules\Project\App\Http\Requests\ConfirmImportTaskRequest;
use Modules\Project\App\Http\Requests\ExportTaskRequest;
use Modules\Project\App\Http\Requests\ImportTaskRequest;
use Modules\Project\App\Http\Requests\ResolveImportTaskRowRequest;
use Modules\Project\App\Http\Requests\StoreTaskRequest;
use Modules\Project\App\Http\Requests\UpdateTaskRequest;
use Modules\Project\App\Models\Task;
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
        $filters = $request->only([
            'project_id', 'assignee_id', 'manager_id', 'status', 'type', 'progress_type',
            'is_overdue', 'date_from', 'date_to', 'q', 'tab', 'sort_by', 'sort_dir',
        ]);
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

    /**
     * PUT /api/project/tasks/{task} — implicit model binding (khác int
     * $task ở show/destroy): UpdateTaskRequest cần đọc progress_type hiện
     * có trên $task để validate đúng khi client chỉ gửi một phần field.
     */
    public function update(UpdateTaskRequest $request, Task $task)
    {
        $result = $this->service->update($task, $request->validated(), $request->user());

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

    /** PATCH /api/project/tasks/bulk — chỉ manager_id/weight (whitelist). */
    public function bulkUpdate(BulkUpdateTaskRequest $request)
    {
        $validated = $request->validated();
        $taskIds = $validated['task_ids'];
        unset($validated['task_ids']);

        $updated = $this->service->bulkUpdate($taskIds, $validated, $request->user());

        return response()->json([
            'tasks' => collect($updated)->map(fn ($t) => $this->service->present($t))->values(),
        ]);
    }

    /** PATCH /api/project/tasks/bulk-delegate — chuyển giao hàng loạt (Phase 3 §6). */
    public function bulkDelegate(BulkDelegateTaskRequest $request)
    {
        $validated = $request->validated();

        $updated = $this->service->bulkDelegate(
            $validated['task_ids'],
            (int) $validated['delegated_to_employee_id'],
            $request->user(),
        );

        return response()->json([
            'tasks' => collect($updated)->map(fn ($t) => $this->service->present($t))->values(),
        ]);
    }

    /** GET /api/project/tasks/export — tôn trọng đúng bộ lọc đang áp dụng trên trang danh sách. */
    public function export(ExportTaskRequest $request)
    {
        return $this->service->export($request->filters(), $request->columns(), $request->user());
    }

    /** POST /api/project/tasks/import/preview — đọc + xem trước, KHÔNG ghi DB. */
    public function importPreview(ImportTaskRequest $request)
    {
        $result = $this->service->previewImport($request->file('file'), $request->user());

        return response()->json($result);
    }

    /** POST /api/project/tasks/import/resolve-row — sửa lỗi 1 dòng tại chỗ, re-resolve. */
    public function importResolveRow(ResolveImportTaskRowRequest $request)
    {
        $result = $this->service->resolveImportRow($request->validated(), $request->user());

        return response()->json($result);
    }

    /** POST /api/project/tasks/import/confirm — nhận JSON các dòng đã preview, ghi DB thật. */
    public function importConfirm(ConfirmImportTaskRequest $request)
    {
        $result = $this->service->confirmImport($request->validated()['rows'], $request->user());

        return response()->json($result);
    }
}
