<?php

namespace Modules\Dashboard\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Dashboard\App\Repositories\Contracts\MyDashboardRepositoryInterface;
use Modules\Project\App\Models\Task;

/**
 * Tầng duy nhất gọi Eloquent trực tiếp cho Dashboard cá nhân. Chỉ đọc.
 */
class MyDashboardRepository implements MyDashboardRepositoryInterface
{
    public function tasksForUser(int $userId): Collection
    {
        return Task::query()
            ->where('type', 'task')
            ->where('assignee_id', $userId)
            ->with('project:id,name')
            ->select(['id', 'title', 'project_id', 'assignee_id', 'status', 'progress_percent', 'start_date', 'end_date', 'due_time', 'updated_at'])
            ->orderByDesc('updated_at')
            ->get();
    }
}
