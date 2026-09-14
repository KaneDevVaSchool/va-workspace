<?php

namespace Modules\Project\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Project\App\Http\Requests\StoreTaskWorklogRequest;
use Modules\Project\App\Http\Requests\UpdateTaskWorklogRequest;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Models\TaskWorklog;
use Modules\Project\App\Services\TaskWorklogService;
use Modules\Identity\App\Services\ActivityLogService;

/**
 * Controller mỏng: chỉ nhận request, gọi Service, trả response.
 */
class TaskWorklogController extends Controller
{
    public function __construct(
        private readonly TaskWorklogService $service,
        private readonly ActivityLogService $activityLogs,
    ) {}

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

        $this->activityLogs->record(
            'task_worklog.create',
            "Ghi nhật ký giờ làm cho công việc \"{$task->title}\"",
            $request->user(),
            'task',
            $task->id,
        );

        return response()->json(['worklog' => $this->service->present($log)], 201);
    }

    /** PUT /api/project/tasks/worklogs/{worklog} */
    public function update(UpdateTaskWorklogRequest $request, TaskWorklog $worklog)
    {
        $taskName = $worklog->task->title;
        $taskId = $worklog->task_id;
        $result = $this->service->update($worklog, $request->validated(), $request->user());

        if (is_array($result)) {
            return response()->json(['message' => $result['error']], 403);
        }

        $this->activityLogs->record(
            'task_worklog.update',
            "Cập nhật nhật ký giờ làm của công việc \"{$taskName}\"",
            $request->user(),
            'task',
            $taskId,
        );

        return response()->json(['worklog' => $this->service->present($result)]);
    }

    /** DELETE /api/project/tasks/worklogs/{worklog} */
    public function destroy(TaskWorklog $worklog, Request $request)
    {
        $taskName = $worklog->task->title;
        $taskId = $worklog->task_id;
        $error = $this->service->delete($worklog, $request->user());
        if ($error !== null) {
            return response()->json(['message' => $error['error']], 403);
        }

        $this->activityLogs->record(
            'task_worklog.delete',
            "Xoá nhật ký giờ làm của công việc \"{$taskName}\"",
            $request->user(),
            'task',
            $taskId,
        );

        return response()->json(['message' => 'Đã xoá nhật ký giờ làm.']);
    }
}
