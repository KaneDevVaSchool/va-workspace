<?php

namespace Modules\Dashboard\App\Services;

use Illuminate\Support\Carbon;
use Modules\Dashboard\App\Repositories\Contracts\MyDashboardRepositoryInterface;
use Modules\Project\App\Enums\TaskEnums;

/**
 * Business logic Dashboard cá nhân — Controller chỉ gọi qua đây. Luôn scope
 * theo đúng 1 userId (chính viewer), không nhận tham số nào khác.
 */
class MyDashboardService
{
    public function __construct(
        private readonly MyDashboardRepositoryInterface $repository,
    ) {}

    public function overview(int $userId): array
    {
        $tasks = $this->repository->tasksForUser($userId);

        $tasksByStatus = array_fill_keys(TaskEnums::STATUSES, 0);
        $progressValues = [];
        $overdueTasksCount = 0;
        $today = Carbon::today();
        $taskList = [];

        foreach ($tasks as $task) {
            $tasksByStatus[$task->status] = ($tasksByStatus[$task->status] ?? 0) + 1;

            if ($task->progress_percent !== null && $task->status !== 'cancelled') {
                $progressValues[] = (float) $task->progress_percent;
            }

            $isUnfinished = ! in_array($task->status, ['completed', 'cancelled'], true);
            $isOverdue = $isUnfinished && ! empty($task->end_date) && Carbon::parse($task->end_date)->lt($today);
            if ($isOverdue) {
                $overdueTasksCount++;
            }

            $taskList[] = [
                'id' => $task->id,
                'title' => $task->title,
                'project_name' => $task->project->name ?? null,
                'status' => $task->status,
                'status_label' => TaskEnums::STATUS_LABELS[$task->status] ?? $task->status,
                'progress_percent' => $task->progress_percent,
                'end_date' => $task->end_date,
                'is_overdue' => $isOverdue,
            ];
        }

        $averageProgress = count($progressValues) > 0
            ? round(array_sum($progressValues) / count($progressValues), 1)
            : null;

        return [
            'kpis' => [
                'total_tasks' => $tasks->count(),
                'average_progress_percent' => $averageProgress,
                'overdue_tasks_count' => $overdueTasksCount,
            ],
            'tasks_status_breakdown' => $tasksByStatus,
            'tasks' => $taskList,
        ];
    }
}
