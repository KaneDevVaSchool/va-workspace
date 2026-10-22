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
            ->with(['project:id,name', 'creator:id,name'])
            ->select([
                'id', 'title', 'project_id', 'assignee_id', 'created_by', 'status',
                'progress_percent', 'start_date', 'start_time', 'end_date', 'due_time',
                'accepted_at', 'updated_at',
            ])
            ->orderByDesc('updated_at')
            ->get();
    }
}
