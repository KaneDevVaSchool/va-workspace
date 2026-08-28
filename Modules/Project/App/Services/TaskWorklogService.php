<?php

namespace Modules\Project\App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Identity\App\Services\NotificationService;
use Modules\Identity\App\Services\PermissionService;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Models\TaskWorklog;
use Modules\Project\App\Repositories\Contracts\TaskWorklogRepositoryInterface;

/**
 * Business logic của Worklog chấm công giờ thực tế (Nhóm E). Mỗi người chỉ
 * tự ghi giờ của chính mình — user_id luôn = người đăng nhập, không nhận
 * từ client. Sửa/xoá chỉ cho phép chủ log hoặc người có task.approve.
 * KHÔNG có rate_snapshot/chi phí — thuộc ProjectFinance (chưa dựng).
 */
class TaskWorklogService
{
    public function __construct(
        private readonly TaskWorklogRepositoryInterface $worklogs,
        private readonly PermissionService $permissions,
        private readonly NotificationService $notifications,
    ) {}

    /** @return Collection<int, TaskWorklog> */
    public function listForTask(Task $task): Collection
    {
        return $this->worklogs->listForTask($task->id);
    }

    /** @param  array<string, mixed>  $data */
    public function create(Task $task, array $data, User $creator): TaskWorklog
    {
        $log = $this->worklogs->create(array_merge($data, [
            'task_id' => $task->id,
            'user_id' => $creator->id, // luôn = người ghi, không nhận từ client
            'created_by' => $creator->id,
        ]));

        $this->notifyWorklogAdded($task, $log, $creator);

        return $log;
    }

    /**
     * Báo cho manager (nếu có) hoặc creator (fallback) của task — chính
     * người vừa ghi log không tự nhận thông báo (NotificationService tự
     * loại trừ actor). Không chặn luồng chính nếu gửi thông báo lỗi.
     */
    private function notifyWorklogAdded(Task $task, TaskWorklog $log, User $actor): void
    {
        $recipientId = $task->manager_id ?? $task->created_by;
        if ($recipientId === null) {
            return;
        }

        $this->notifications->notifyUsers(
            [(int) $recipientId],
            $actor,
            NotificationService::TYPE_TASK_WORKLOG_ADDED,
            "Có giờ làm mới trên công việc \"{$task->title}\"",
            "{$actor->name} đã ghi {$log->hours} giờ làm.",
            "/manager/project/tasks?task={$task->id}",
            ['task_id' => $task->id, 'worklog_id' => $log->id],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TaskWorklog|array{error: string}
     */
    public function update(TaskWorklog $log, array $data, User $editor): TaskWorklog|array
    {
        if (! $this->canManage($log, $editor)) {
            return ['error' => 'Bạn không thể sửa nhật ký giờ làm của người khác.'];
        }

        return $this->worklogs->update($log, $data);
    }

    /** @return array{error: string}|null */
    public function delete(TaskWorklog $log, User $editor): ?array
    {
        if (! $this->canManage($log, $editor)) {
            return ['error' => 'Bạn không thể xoá nhật ký giờ làm của người khác.'];
        }

        $this->worklogs->delete($log);

        return null;
    }

    private function canManage(TaskWorklog $log, User $editor): bool
    {
        return $log->created_by === $editor->id
            || $this->permissions->allows($editor, 'task.approve');
    }

    public function present(TaskWorklog $log): array
    {
        return [
            'id' => $log->id,
            'task_id' => $log->task_id,
            'user_id' => $log->user_id,
            'user' => $this->presentUser($log->relationLoaded('user') ? $log->user : null),
            'work_date' => $log->work_date?->toDateString(),
            'hours' => $log->hours,
            'note' => $log->note,
            'created_by' => $log->created_by,
            'created_at' => $log->created_at?->toIso8601String(),
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
