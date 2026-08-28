<?php

namespace Modules\Project\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Project\App\Models\TaskWorklog;
use Modules\Project\App\Repositories\Contracts\TaskWorklogRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent trực tiếp cho entity TaskWorklog.
 */
class TaskWorklogRepository implements TaskWorklogRepositoryInterface
{
    public function listForTask(int $taskId): Collection
    {
        return TaskWorklog::query()
            ->with('user')
            ->where('task_id', $taskId)
            ->orderByDesc('work_date')
            ->orderByDesc('created_at')
            ->get();
    }

    public function find(int $id): ?TaskWorklog
    {
        return TaskWorklog::query()->with('user')->find($id);
    }

    public function create(array $data): TaskWorklog
    {
        $log = TaskWorklog::query()->create($data);

        return $log->fresh('user');
    }

    public function update(TaskWorklog $log, array $data): TaskWorklog
    {
        $log->update($data);

        return $log->fresh('user');
    }

    public function delete(TaskWorklog $log): bool
    {
        return (bool) $log->delete();
    }
}
