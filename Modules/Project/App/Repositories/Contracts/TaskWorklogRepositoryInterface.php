<?php

namespace Modules\Project\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Project\App\Models\TaskWorklog;

/**
 * Contract cho tầng Repository của TaskWorklog — Service chỉ phụ thuộc
 * interface này, không phụ thuộc trực tiếp Eloquent.
 */
interface TaskWorklogRepositoryInterface
{
    public function listForTask(int $taskId): Collection;

    public function find(int $id): ?TaskWorklog;

    /** @param  array<string, mixed>  $data */
    public function create(array $data): TaskWorklog;

    /** @param  array<string, mixed>  $data */
    public function update(TaskWorklog $log, array $data): TaskWorklog;

    public function delete(TaskWorklog $log): bool;
}
