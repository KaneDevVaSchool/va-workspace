<?php

namespace Modules\Project\App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Project\App\Enums\TaskEnums;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Repositories\Contracts\TaskRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent trực tiếp cho entity Task.
 */
class TaskRepository implements TaskRepositoryInterface
{
    public function paginate(array $filters, int $perPage, int $page, array $allowedProjectIds, User $viewer): LengthAwarePaginator
    {
        $query = Task::query()->with(Task::WITH_PRESENT)->whereIn('project_id', $allowedProjectIds);

        $this->applyFilters($query, $filters);
        $this->applyTabFilter($query, $filters['tab'] ?? null, $viewer);

        return $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);
    }

    public function tabCounts(array $allowedProjectIds, ?int $forceAssigneeId, User $viewer): array
    {
        $tabs = ['all', 'not_started', 'in_progress', 'on_hold', 'completed', 'cancelled', 'my_tasks'];
        $counts = [];

        foreach ($tabs as $tab) {
            $query = Task::query()->whereIn('project_id', $allowedProjectIds);
            if ($forceAssigneeId !== null) {
                $query->where('assignee_id', $forceAssigneeId);
            }
            $this->applyTabFilter($query, $tab === 'all' ? null : $tab, $viewer);
            $counts[$tab] = $query->count();
        }

        return $counts;
    }

    public function flatForProject(int $projectId): Collection
    {
        return Task::query()
            ->with(Task::WITH_PRESENT)
            ->where('project_id', $projectId)
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->get();
    }

    public function find(int $id): ?Task
    {
        return Task::query()->with(Task::WITH_PRESENT)->find($id);
    }

    public function create(array $data): Task
    {
        // origin_department_id / delegated_to_* / delegation_status cố ý
        // KHÔNG nằm trong Task::$fillable (chừa chỗ Task Delegation, §6 —
        // không set qua form thường) — tách riêng để Service vẫn set được
        // origin_department_id mặc định lúc tạo mà không mở fillable cho cả
        // 4 cột nhạy cảm đó qua mass assignment thường.
        $guarded = array_intersect_key($data, array_flip(['origin_department_id']));
        $fillableData = array_diff_key($data, $guarded);

        $task = Task::query()->create($fillableData);

        if ($guarded !== []) {
            $task->forceFill($guarded)->save();
        }

        return $task->fresh(Task::WITH_PRESENT);
    }

    public function update(Task $task, array $data): Task
    {
        $task->update($data);

        return $task->fresh(Task::WITH_PRESENT);
    }

    public function delete(Task $task): bool
    {
        return (bool) $task->delete();
    }

    public function hasChildren(int $taskId): bool
    {
        return Task::query()->where('parent_id', $taskId)->exists();
    }

    public function descendantIds(int $taskId): array
    {
        $ids = [];
        $frontier = [$taskId];

        while ($frontier !== []) {
            $children = Task::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            if ($children === []) {
                break;
            }
            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        return $ids;
    }

    public function countByProject(int $projectId): int
    {
        return Task::query()->where('project_id', $projectId)->count();
    }

    /** @param  array<string, mixed>  $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['project_id'])) {
            $query->where('project_id', (int) $filters['project_id']);
        }

        if (! empty($filters['assignee_id'])) {
            $query->where('assignee_id', (int) $filters['assignee_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('start_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('end_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            if ($q !== '') {
                $query->where('title', 'like', '%'.$q.'%');
            }
        }
    }

    private function applyTabFilter(Builder $query, ?string $tab, ?User $viewer): void
    {
        if ($tab === null || $tab === '' || $tab === 'all') {
            return;
        }

        if (in_array($tab, TaskEnums::STATUSES, true)) {
            $query->where('status', $tab);

            return;
        }

        if ($tab === 'my_tasks' && $viewer !== null) {
            $query->where('assignee_id', $viewer->id);
        }
    }
}
