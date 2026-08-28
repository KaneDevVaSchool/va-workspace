<?php

namespace Modules\Project\App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Services\PermissionService;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Models\TaskScore;
use Modules\Project\App\Repositories\Contracts\ProjectRepositoryInterface;
use Modules\Project\App\Repositories\Contracts\TaskRepositoryInterface;

/**
 * Business logic của entity Task (Project Giai đoạn 2 — WBS đa cấp).
 *
 * Quyền xem Task = quyền xem Project chứa nó — KHÔNG viết lại RBAC riêng
 * cho Task, tái dùng ProjectRepositoryInterface::forViewer().
 */
class TaskService
{
    /** Ngưỡng cảnh báo mềm độ sâu WBS — không chặn cứng (docs/VA_WORKSPACE_OVERVIEW.md §20.9). */
    private const DEPTH_WARNING_THRESHOLD = 6;

    public function __construct(
        private readonly TaskRepositoryInterface $tasks,
        private readonly ProjectRepositoryInterface $projects,
        private readonly PermissionService $permissions,
    ) {}

    /** @param  array<string, mixed>  $filters */
    public function paginate(array $filters, int $perPage, int $page, User $viewer): LengthAwarePaginator
    {
        // task.view_assigned (không có task.view/task.*) → chỉ xem Task giao cho chính mình,
        // bất kể query gửi gì.
        if (! $this->permissions->allows($viewer, 'task.view') && ! $this->permissions->allows($viewer, 'task.*')) {
            $filters['assignee_id'] = $viewer->id;
        }

        $allowedProjectIds = $this->projects->forViewer(Project::query(), $viewer)->pluck('id')->all();

        return $this->tasks->paginate($filters, $perPage, $page, $allowedProjectIds, $viewer);
    }

    /** @return array<string, int> */
    public function tabCounts(User $viewer): array
    {
        $forceAssigneeId = null;
        if (! $this->permissions->allows($viewer, 'task.view') && ! $this->permissions->allows($viewer, 'task.*')) {
            $forceAssigneeId = $viewer->id;
        }

        $allowedProjectIds = $this->projects->forViewer(Project::query(), $viewer)->pluck('id')->all();

        return $this->tasks->tabCounts($allowedProjectIds, $forceAssigneeId, $viewer);
    }

    public function find(int $id): ?Task
    {
        return $this->tasks->find($id);
    }

    /**
     * Cây WBS đầy đủ của 1 project, kèm wbs_code tính runtime (không lưu
     * cột — docs/VA_WORKSPACE_OVERVIEW.md §16.1).
     *
     * @return list<array<string, mixed>>
     */
    public function treeForProject(Project $project): array
    {
        $flat = $this->tasks->flatForProject($project->id);

        $byParent = [];
        foreach ($flat as $task) {
            $key = $task->parent_id ?? 0;
            $byParent[$key][] = $task;
        }

        return $this->buildWbsTree($byParent, 0, '');
    }

    /**
     * @param  array<int, list<Task>>  $byParent
     * @return list<array<string, mixed>>
     */
    private function buildWbsTree(array $byParent, int $parentKey, string $parentWbs): array
    {
        $siblings = $byParent[$parentKey] ?? [];
        $result = [];

        foreach (array_values($siblings) as $index => $task) {
            $wbsCode = $parentWbs === '' ? (string) ($index + 1) : $parentWbs.'.'.($index + 1);
            $node = $this->present($task);
            $node['wbs_code'] = $wbsCode;
            $node['children'] = $this->buildWbsTree($byParent, $task->id, $wbsCode);
            $result[] = $node;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Task|array{error: string}
     */
    public function create(Project $project, array $data, User $creator): Task|array
    {
        $titles = $data['titles'] ?? null;
        if (is_array($titles) && $titles !== []) {
            $last = null;
            foreach ($titles as $title) {
                $last = $this->createSingle($project, array_merge($data, ['title' => $title]), $creator);
                if (is_array($last)) {
                    return $last;
                }
            }

            return $last;
        }

        return $this->createSingle($project, $data, $creator);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Task|array{error: string}
     */
    private function createSingle(Project $project, array $data, User $creator): Task|array
    {
        $parentId = $data['parent_id'] ?? null;
        if ($parentId !== null) {
            $parent = $this->tasks->find((int) $parentId);
            if ($parent === null || $parent->project_id !== $project->id) {
                return ['error' => 'Công việc cha không thuộc dự án này.'];
            }
        }

        unset($data['titles']);

        $data = $this->applyQuantityProgress($data);

        $payload = array_merge($data, [
            'project_id' => $project->id,
            'type' => $data['type'] ?? 'task',
            'status' => $data['status'] ?? 'not_started',
            'origin_department_id' => $project->owner_department_id,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);

        return $this->tasks->create($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Task|array{error: string}
     */
    public function update(Task $task, array $data, User $editor): Task|array
    {
        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            $newParentId = (int) $data['parent_id'];

            if ($newParentId === $task->id) {
                return ['error' => 'Không thể chọn chính công việc này làm công việc cha.'];
            }

            $descendantIds = $this->tasks->descendantIds($task->id);
            if (in_array($newParentId, $descendantIds, true)) {
                return ['error' => 'Không thể chọn công việc con của chính nó làm công việc cha.'];
            }

            $newParent = $this->tasks->find($newParentId);
            if ($newParent === null || $newParent->project_id !== $task->project_id) {
                return ['error' => 'Công việc cha không thuộc cùng dự án.'];
            }
        }

        $data = $this->applyQuantityProgress($data, $task);
        $data = $this->applyAcceptedTracking($task, $data);

        $data['updated_by'] = $editor->id;

        return $this->tasks->update($task, $data);
    }

    /**
     * Nhóm H — khi progress_type=quantity, tự tính progress_percent =
     * round(progress_number / progress_total * 100), ghi đè giá trị client
     * gửi (Request đã prohibited progress_percent trong trường hợp này nên
     * $data không có sẵn field đó — hàm này là nguồn thật duy nhất).
     * Không lưu trùng công thức — chỉ tính khi có đủ number + total > 0.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyQuantityProgress(array $data, ?Task $existing = null): array
    {
        $type = $data['progress_type'] ?? $existing?->progress_type ?? 'percent';
        if ($type !== 'quantity') {
            return $data;
        }

        $number = $data['progress_number'] ?? $existing?->progress_number;
        $total = $data['progress_total'] ?? $existing?->progress_total;

        if ($number !== null && $total !== null && (float) $total > 0) {
            $data['progress_percent'] = (int) round(((float) $number / (float) $total) * 100);
        }

        return $data;
    }

    /**
     * Nhóm F — "Người đã nhận thực hiện": derived field, tự set khi status
     * rời 'not_started' lần đầu tiên (accepted_by còn null). Không có input
     * UI cho field này — set duy nhất ở đây.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyAcceptedTracking(Task $task, array $data): array
    {
        if (! array_key_exists('status', $data)) {
            return $data;
        }

        if ($task->status === 'not_started' && $data['status'] !== 'not_started' && $task->accepted_by === null) {
            $data['accepted_by'] = $task->assignee_id ?? $data['assignee_id'] ?? null;
            $data['accepted_at'] = now();
        }

        return $data;
    }

    /** @return array{error: string}|true */
    public function delete(Task $task): array|bool
    {
        if ($this->tasks->hasChildren($task->id)) {
            return ['error' => 'Xoá các công việc con trước khi xoá mục này.'];
        }

        return $this->tasks->delete($task);
    }

    public function countByProject(int $projectId): int
    {
        return $this->tasks->countByProject($projectId);
    }

    public function present(Task $task): array
    {
        $overdue = $this->computeOverdue($task);

        return [
            'id' => $task->id,
            'project_id' => $task->project_id,
            'project' => $task->relationLoaded('project') && $task->project !== null
                ? ['id' => $task->project->id, 'code' => $task->project->code, 'name' => $task->project->name]
                : null,
            'parent_id' => $task->parent_id,
            'parent' => $task->relationLoaded('parent') && $task->parent !== null
                ? ['id' => $task->parent->id, 'code' => $task->parent->code, 'title' => $task->parent->title]
                : null,
            'code' => $task->code,
            'type' => $task->type,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'priority' => $task->priority,
            'start_date' => $task->start_date?->toDateString(),
            'start_time' => $task->start_time,
            'end_date' => $task->end_date?->toDateString(),
            'due_time' => $task->due_time,
            'actual_start_date' => $task->actual_start_date?->toDateString(),
            'actual_end_date' => $task->actual_end_date?->toDateString(),
            'assignee_id' => $task->assignee_id,
            'assignee' => $this->presentUser($task->relationLoaded('assignee') ? $task->assignee : null),
            'progress_percent' => $task->progress_percent,
            'progress_type' => $task->progress_type,
            'progress_number' => $task->progress_number,
            'progress_total' => $task->progress_total,
            'unit' => $task->unit,
            'estimated_hours' => $task->estimated_hours,
            'worklog_hours' => (float) ($task->worklogs_sum_hours ?? 0),
            'manager_id' => $task->manager_id,
            'manager' => $this->presentUser($task->relationLoaded('manager') ? $task->manager : null),
            'accepted_by' => $task->accepted_by,
            'accepted_by_user' => $this->presentUser($task->relationLoaded('acceptedBy') ? $task->acceptedBy : null),
            'accepted_at' => $task->accepted_at?->toIso8601String(),
            'weight' => $task->weight,
            'sort_order' => $task->sort_order,
            'attachments_count' => $task->attachments_count ?? 0,
            'is_overdue' => $overdue['is_overdue'],
            'overdue_days' => $overdue['overdue_days'],
            'variance_days' => $overdue['variance_days'],
            'task_score' => $this->presentTaskScore($task->relationLoaded('taskScore') ? $task->taskScore : null),
            'creator' => $this->presentUser($task->relationLoaded('creator') ? $task->creator : null),
            'created_by' => $task->created_by,
            'updated_by' => $task->updated_by,
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Nhóm A — is_overdue/overdue_days/variance_days tính từ 1 nguồn công
     * thức duy nhất, tránh trùng lặp (docs/VA_WORKSPACE_OVERVIEW.md §17):
     *
     * - is_overdue: end_date đã qua hôm nay và trạng thái chưa hoàn thành/huỷ.
     *   PHẢI khớp TaskRepository::scopeOverdue() dùng cho tab/filter "Quá hạn".
     * - variance_days: actual_end_date - end_date (dương = trễ, âm = sớm),
     *   null nếu thiếu 1 trong 2 mốc.
     * - overdue_days: chỉ có ý nghĩa khi task đã completed VÀ variance dương
     *   (hoàn thành trễ) — khác is_overdue (task đang mở, chưa xong, đã qua hạn).
     *
     * @return array{is_overdue: bool, overdue_days: int|null, variance_days: int|null}
     */
    private function computeOverdue(Task $task): array
    {
        $today = now()->startOfDay();
        $isOverdue = $task->end_date !== null
            && $task->end_date->lt($today)
            && ! in_array($task->status, ['completed', 'cancelled'], true);

        $varianceDays = null;
        if ($task->actual_end_date !== null && $task->end_date !== null) {
            $varianceDays = $task->end_date->diffInDays($task->actual_end_date, false);
        }

        $overdueDays = ($task->status === 'completed' && $varianceDays !== null && $varianceDays > 0)
            ? $varianceDays
            : null;

        return ['is_overdue' => $isOverdue, 'overdue_days' => $overdueDays, 'variance_days' => $varianceDays];
    }

    private function presentTaskScore(?TaskScore $score): ?array
    {
        if ($score === null) {
            return null;
        }

        return [
            'rating_score' => $score->rating_score,
            'rating_result' => $score->rating_result,
            'rating_desc' => $score->rating_desc,
            'scored_by' => $score->scored_by,
            'scored_at' => $score->scored_at?->toIso8601String(),
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
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
        ];
    }
}
