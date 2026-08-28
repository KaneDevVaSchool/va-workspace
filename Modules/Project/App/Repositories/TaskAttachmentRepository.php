<?php

namespace Modules\Project\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Project\App\Models\TaskAttachment;
use Modules\Project\App\Repositories\Contracts\TaskAttachmentRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent trực tiếp cho entity TaskAttachment.
 */
class TaskAttachmentRepository implements TaskAttachmentRepositoryInterface
{
    public function listForTask(int $taskId): Collection
    {
        return TaskAttachment::query()
            ->with('uploader')
            ->where('task_id', $taskId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function find(int $id): ?TaskAttachment
    {
        return TaskAttachment::query()->with('uploader')->find($id);
    }

    public function create(array $data): TaskAttachment
    {
        $attachment = TaskAttachment::query()->create($data);

        return $attachment->fresh('uploader');
    }

    public function delete(TaskAttachment $attachment): bool
    {
        return (bool) $attachment->delete();
    }
}
