<?php

namespace Modules\Project\App\Repositories\Contracts;

use Modules\Project\App\Models\TaskScore;

/**
 * Contract cho tầng Repository của TaskScore — Service chỉ phụ thuộc
 * interface này, không phụ thuộc trực tiếp Eloquent.
 */
interface TaskScoreRepositoryInterface
{
    public function findForTask(int $taskId): ?TaskScore;

    /**
     * Upsert theo task_id (unique) — 1 task chỉ có 1 bản ghi điểm hiện
     * hành, gọi lại sẽ ghi đè thay vì tạo dòng lịch sử mới.
     *
     * @param  array<string, mixed>  $data
     */
    public function upsertForTask(int $taskId, array $data): TaskScore;
}
