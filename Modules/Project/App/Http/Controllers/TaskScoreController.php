<?php

namespace Modules\Project\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Project\App\Http\Requests\UpsertTaskScoreRequest;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Services\TaskScoreService;

/**
 * Controller mỏng: chỉ nhận request, gọi Service, trả response.
 */
class TaskScoreController extends Controller
{
    public function __construct(private readonly TaskScoreService $service) {}

    /** GET /api/project/tasks/{task}/score — ai có task.view cũng xem được. */
    public function show(Task $task)
    {
        $score = $this->service->findForTask($task);

        return response()->json(['task_score' => $score ? $this->service->present($score) : null]);
    }

    /** PUT /api/project/tasks/{task}/score — chỉ task.approve mới chấm/sửa. */
    public function upsert(UpsertTaskScoreRequest $request, Task $task)
    {
        $score = $this->service->upsert($task, $request->validated(), $request->user());

        return response()->json(['task_score' => $this->service->present($score)]);
    }
}
