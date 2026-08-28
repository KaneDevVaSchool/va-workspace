<?php

namespace Modules\Project\App\Services;

use App\Models\User;
use Modules\Identity\App\Services\NotificationService;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Models\TaskScore;
use Modules\Project\App\Repositories\Contracts\TaskScoreRepositoryInterface;

/**
 * Business logic của Đánh giá tối thiểu (Nhóm G — reference/phiếu chấm đơn
 * giản, KHÔNG phải TaskScoringConfig/Kpi đầy đủ, xem
 * docs/VA_WORKSPACE_OVERVIEW.md §7). 1 task chỉ có 1 bản ghi hiện hành —
 * upsert ghi đè, không lưu lịch sử nhiều dòng.
 */
class TaskScoreService
{
    public function __construct(
        private readonly TaskScoreRepositoryInterface $scores,
        private readonly NotificationService $notifications,
    ) {}

    public function findForTask(Task $task): ?TaskScore
    {
        return $this->scores->findForTask($task->id);
    }

    /** @param  array<string, mixed>  $data */
    public function upsert(Task $task, array $data, User $scorer): TaskScore
    {
        $score = $this->scores->upsertForTask($task->id, array_merge($data, [
            'scored_by' => $scorer->id,
            'scored_at' => now(),
        ]));

        $this->notifyScored($task, $scorer);

        return $score;
    }

    /**
     * Báo cho assignee của task — người được chấm điểm cần biết kết quả.
     * Không chặn luồng chính nếu gửi thông báo lỗi.
     */
    private function notifyScored(Task $task, User $actor): void
    {
        if ($task->assignee_id === null) {
            return;
        }

        $this->notifications->notifyUsers(
            [(int) $task->assignee_id],
            $actor,
            NotificationService::TYPE_TASK_SCORED,
            "Công việc \"{$task->title}\" đã được đánh giá",
            "{$actor->name} vừa chấm điểm công việc của bạn.",
            "/manager/project/tasks?task={$task->id}",
            ['task_id' => $task->id],
        );
    }

    public function present(TaskScore $score): array
    {
        return [
            'id' => $score->id,
            'task_id' => $score->task_id,
            'rating_score' => $score->rating_score,
            'rating_result' => $score->rating_result,
            'rating_desc' => $score->rating_desc,
            'scored_by' => $score->scored_by,
            'scored_at' => $score->scored_at?->toIso8601String(),
        ];
    }
}
