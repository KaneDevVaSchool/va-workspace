<?php

namespace Modules\Project\App\Repositories;

use Modules\Project\App\Models\TaskScore;
use Modules\Project\App\Repositories\Contracts\TaskScoreRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent trực tiếp cho entity TaskScore.
 */
class TaskScoreRepository implements TaskScoreRepositoryInterface
{
    public function findForTask(int $taskId): ?TaskScore
    {
        return TaskScore::query()->where('task_id', $taskId)->first();
    }

    public function upsertForTask(int $taskId, array $data): TaskScore
    {
        $score = TaskScore::query()->updateOrCreate(['task_id' => $taskId], $data);

        return $score->fresh();
    }
}
