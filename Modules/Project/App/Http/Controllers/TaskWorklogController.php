<?php

namespace Modules\Project\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Project\App\Http\Requests\StoreTaskWorklogRequest;
use Modules\Project\App\Http\Requests\UpdateTaskWorklogRequest;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Models\TaskWorklog;
use Modules\Project\App\Services\TaskWorklogService;

/**
 * Controller mỏng: chỉ nhận request, gọi Service, trả response.
 */
class TaskWorklogController extends Controller
{
    public function __construct(private readonly TaskWorklogService $service) {}

    /** GET /api/project/tasks/{task}/worklogs */
    public function index(Task $task)
    {
        $logs = $this->service->listForTask($task);

        return response()->json([
            'worklogs' => $logs->map(fn (TaskWorklog $l) => $this->service->present($l))->values(),
        ]);
    }

    /** POST /api/project/tasks/{task}/worklogs */
    public function store(StoreTaskWorklogRequest $request, Task $task)
    {
        $log = $this->service->create($task, $request->validated(), $request->user());

        return response()->json(['worklog' => $this->service->present($log)], 201);
    }

    /** PUT /api/project/tasks/worklogs/{worklog} */
    public function update(UpdateTaskWorklogRequest $request, TaskWorklog $worklog)
    {
        $result = $this->service->update($worklog, $request->validated(), $request->user());

        if (is_array($result)) {
            return response()->json(['message' => $result['error']], 403);
        }

        return response()->json(['worklog' => $this->service->present($result)]);
    }

    /** DELETE /api/project/tasks/worklogs/{worklog} */
    public function destroy(TaskWorklog $worklog, Request $request)
    {
        $error = $this->service->delete($worklog, $request->user());
        if ($error !== null) {
            return response()->json(['message' => $error['error']], 403);
        }

        return response()->json(['message' => 'Đã xoá nhật ký giờ làm.']);
    }
}
