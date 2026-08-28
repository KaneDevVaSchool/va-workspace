<?php

namespace Modules\Project\App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Models\TaskAttachment;
use Modules\Project\App\Repositories\Contracts\TaskAttachmentRepositoryInterface;

/**
 * Business logic của Đính kèm công việc (Nhóm D — bản tối thiểu, chỉ file
 * upload, KHÔNG có kind/drive_link/url như ProjectAttachment).
 */
class TaskAttachmentService
{
    public function __construct(
        private readonly TaskAttachmentRepositoryInterface $attachments,
    ) {}

    /** @return Collection<int, TaskAttachment> */
    public function listForTask(Task $task)
    {
        return $this->attachments->listForTask($task->id);
    }

    public function upload(Task $task, UploadedFile $file, User $uploader): TaskAttachment
    {
        $path = $file->store('task/'.$task->id, 'public');

        return $this->attachments->create([
            'task_id' => $task->id,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $uploader->id,
        ]);
    }

    /** @return array{error: string}|null */
    public function delete(int $attachmentId): ?array
    {
        $attachment = $this->attachments->find($attachmentId);
        if ($attachment === null) {
            return ['error' => 'Không tìm thấy tệp đính kèm.'];
        }

        if ($attachment->file_path) {
            Storage::disk('public')->delete($attachment->file_path);
        }
        $this->attachments->delete($attachment);

        return null;
    }

    public function present(TaskAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'task_id' => $attachment->task_id,
            'file_path' => $attachment->file_path,
            'file_url' => $attachment->file_path ? Storage::disk('public')->url($attachment->file_path) : null,
            'file_name' => $attachment->file_name,
            'file_size' => $attachment->file_size,
            'uploaded_by' => $attachment->uploaded_by,
            'uploader' => $this->presentUser($attachment->relationLoaded('uploader') ? $attachment->uploader : null),
            'created_at' => $attachment->created_at?->toIso8601String(),
        ];
    }

    private function presentUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatar_url,
        ];
    }
}
