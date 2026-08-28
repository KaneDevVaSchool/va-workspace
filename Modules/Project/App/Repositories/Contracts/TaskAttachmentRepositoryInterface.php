<?php

namespace Modules\Project\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Project\App\Models\TaskAttachment;

/**
 * Contract cho tầng Repository của TaskAttachment — Service chỉ phụ thuộc
 * interface này, không phụ thuộc trực tiếp Eloquent.
 */
interface TaskAttachmentRepositoryInterface
{
    public function listForTask(int $taskId): Collection;

    public function find(int $id): ?TaskAttachment;

    /** @param  array<string, mixed>  $data */
    public function create(array $data): TaskAttachment;

    public function delete(TaskAttachment $attachment): bool;
}
