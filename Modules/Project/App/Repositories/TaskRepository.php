<?php

namespace Modules\Project\App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Identity\App\Services\PermissionService;
use Modules\Project\App\Enums\TaskEnums;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Repositories\Contracts\TaskRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent trực tiếp cho entity Task.
 */
class TaskRepository implements TaskRepositoryInterface
{
    /**
     * Cột được phép sort — chỉ giá trị đơn giản (ngày/số), không sort theo
     * quan hệ (manager/parent) vì cần join, để lại nếu có nhu cầu rõ sau.
     * key => cột SQL thật (worklog_hours là alias FE, cột SQL thật do
     * withSum('worklogs','hours') sinh ra là worklogs_sum_hours).
     */
    private const SORTABLE_COLUMNS = [
        'end_date' => 'end_date',
        'progress_percent' => 'progress_percent',
        'weight' => 'weight',
        'estimated_hours' => 'estimated_hours',
        'worklog_hours' => 'worklogs_sum_hours',
        'created_at' => 'created_at',
    ];

    public function paginate(array $filters, int $perPage, int $page, array $allowedProjectIds, User $viewer): LengthAwarePaginator
    {
        $query = $this->baseQuery();
        $this->applyViewerScope($query, $allowedProjectIds, $viewer);

        $this->applyFilters($query, $filters);
        $this->applyTabFilter($query, $filters['tab'] ?? null, $viewer);
        $this->applySort($query, $filters['sort_by'] ?? null, $filters['sort_dir'] ?? null);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function forExport(array $filters, array $allowedProjectIds, User $viewer): Collection
    {
        $query = $this->baseQuery();
        $this->applyViewerScope($query, $allowedProjectIds, $viewer);

        $this->applyFilters($query, $filters);
        $this->applyTabFilter($query, $filters['tab'] ?? null, $viewer);
        $this->applySort($query, $filters['sort_by'] ?? null, $filters['sort_dir'] ?? null);

        return $query->get();
    }

    public function forEvaluationPeriod(array $userIds, string $from, string $to, ?int $departmentId = null): Collection
    {
        $query = Task::query()
            ->with(['taskScore', 'project', 'assignee'])
            ->where('type', 'task')
            ->where('status', '!=', 'cancelled')
            ->where(function (Builder $period) use ($from, $to) {
                $period
                    ->whereBetween('actual_end_date', [$from, $to])
                    ->orWhere(function (Builder $fallback) use ($from, $to) {
                        $fallback
                            ->whereNull('actual_end_date')
                            ->whereBetween('end_date', [$from, $to]);
                    });
            });

        if ($userIds !== []) {
            $query->whereIn('assignee_id', $userIds);
        } else {
            $query->whereNotNull('assignee_id');
        }

        if ($departmentId !== null) {
            $this->scopeEvaluationDepartment($query, $departmentId);
        }

        return $query->orderBy('assignee_id')->orderBy('end_date')->get();
    }

    /**
     * Giới hạn công việc về đúng một phòng ban khi chấm điểm.
     *
     * Không có điều kiện này thì nhân viên làm việc cho dự án phòng khác sẽ
     * mang việc đó vào điểm phòng mình. Phòng ban của một công việc xác định
     * theo thứ tự:
     *
     *   1. Đã chuyển giao và người nhận chưa từ chối → phòng NHẬN.
     *   2. Việc thuộc dự án → phòng sở hữu, hoặc phòng thực hiện chính của dự án.
     *   3. Việc đứng riêng → origin_department_id, không có thì theo phòng
     *      ban hiện tại của người thực hiện.
     */
    private function scopeEvaluationDepartment(Builder $query, int $departmentId): void
    {
        $query->where(function (Builder $scope) use ($departmentId) {
            // (1) Việc đã chuyển giao — tính cho phòng nhận.
            $scope->where(function (Builder $delegated) use ($departmentId) {
                $delegated
                    ->where('delegated_to_department_id', $departmentId)
                    ->where(function (Builder $status) {
                        $status->whereNull('delegation_status')
                            ->orWhere('delegation_status', '!=', 'rejected');
                    });
            });

            // Các nhánh còn lại chỉ áp dụng khi việc KHÔNG được tính cho phòng
            // nhận nào khác — nếu không một việc chuyển đi sẽ vẫn còn nằm ở
            // phòng giao và bị đếm hai lần.
            $scope->orWhere(function (Builder $notDelegated) use ($departmentId) {
                $notDelegated->where(function (Builder $free) {
                    $free->whereNull('delegated_to_department_id')
                        ->orWhere('delegation_status', 'rejected');
                });

                $notDelegated->where(function (Builder $owner) use ($departmentId) {
                    // (2) Việc thuộc dự án.
                    $owner->whereHas('project', function (Builder $project) use ($departmentId) {
                        $project->where('owner_department_id', $departmentId)
                            ->orWhere('executing_department_id', $departmentId);
                    });

                    // (3) Việc đứng riêng.
                    $owner->orWhere(function (Builder $standalone) use ($departmentId) {
                        $standalone->whereNull('project_id')
                            ->where(function (Builder $source) use ($departmentId) {
                                $source->where('origin_department_id', $departmentId)
                                    ->orWhere(function (Builder $byAssignee) use ($departmentId) {
                                        $byAssignee->whereNull('origin_department_id')
                                            ->whereHas(
                                                'assignee',
                                                fn (Builder $user) => $user->where('department_id', $departmentId),
                                            );
                                    });
                            });
                    });
                });
            });
        });
    }

    private function applySort(Builder $query, ?string $sortBy, ?string $sortDir): void
    {
        $column = $sortBy !== null ? (self::SORTABLE_COLUMNS[$sortBy] ?? null) : null;
        if ($column === null) {
            $query->orderByDesc('created_at');

            return;
        }

        $direction = $sortDir === 'asc' ? 'asc' : 'desc';
        $query->orderBy($column, $direction);
    }

    public function tabCounts(array $allowedProjectIds, ?int $forceAssigneeId, User $viewer): array
    {
        $tabs = ['all', 'not_started', 'in_progress', 'on_hold', 'completed', 'cancelled', 'my_tasks', 'overdue'];
        $counts = [];

        foreach ($tabs as $tab) {
            $query = Task::query();
            $this->applyViewerScope($query, $allowedProjectIds, $viewer);
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
        return $this->baseQuery()
            ->where('project_id', $projectId)
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->get();
    }

    public function searchParents(
        User $viewer,
        array $allowedProjectIds,
        ?int $projectId,
        string $q,
        int $limit,
        ?int $id = null,
    ): Collection {
        $query = Task::query()->with(['project:id,code,name']);

        if ($id !== null) {
            $query->whereKey($id);
            $this->applyViewerScope($query, $allowedProjectIds, $viewer);

            return $query->limit(1)->get();
        }

        if ($projectId !== null) {
            if (! $this->hasGlobalProjectAccess($viewer) && ! in_array($projectId, $allowedProjectIds, true)) {
                return collect();
            }
            $query->where('project_id', $projectId);
        } else {
            $query->whereNull('project_id');
            $this->constrainStandalone($query, $viewer);
        }

        $q = trim($q);
        if ($q !== '') {
            $query->where(function (Builder $sub) use ($q) {
                $sub->where('title', 'like', '%'.$q.'%')
                    ->orWhere('code', 'like', '%'.$q.'%');
            });
        }

        return $query->orderBy('title')->limit($limit)->get();
    }

    /**
     * Task thuộc dự án viewer được xem, hoặc công việc thường xuyên
     * (project_id null) trong phạm vi phòng ban / người liên quan.
     *
     * @param  list<int>  $allowedProjectIds
     */
    private function applyViewerScope(Builder $query, array $allowedProjectIds, User $viewer): void
    {
        $query->where(function (Builder $scope) use ($allowedProjectIds, $viewer) {
            if ($allowedProjectIds !== []) {
                $scope->whereIn('project_id', $allowedProjectIds);
            }
            $scope->orWhere(function (Builder $standalone) use ($viewer) {
                $standalone->whereNull('project_id');
                $this->constrainStandalone($standalone, $viewer);
            });
        });
    }

    private function constrainStandalone(Builder $query, User $viewer): void
    {
        if ($this->seesAllStandalone($viewer)) {
            return;
        }

        $query->where(function (Builder $access) use ($viewer) {
            if ($viewer->department_id) {
                $access->where('origin_department_id', $viewer->department_id);
            }
            $access->orWhere('assignee_id', $viewer->id)
                ->orWhere('manager_id', $viewer->id)
                ->orWhere('created_by', $viewer->id)
                ->orWhereHas('watchers', fn (Builder $w) => $w->where('users.id', $viewer->id))
                ->orWhereHas('collaborators', fn (Builder $c) => $c->where('users.id', $viewer->id));
        });
    }

    private function seesAllStandalone(User $viewer): bool
    {
        return $this->hasGlobalProjectAccess($viewer)
            || app(PermissionService::class)->allows($viewer, 'task.*');
    }

    private function hasGlobalProjectAccess(User $viewer): bool
    {
        return $viewer->isSuperAdmin() || app(PermissionService::class)->allows($viewer, 'project.*');
    }

    public function find(int $id): ?Task
    {
        return $this->baseQuery()->find($id);
    }

    public function findByCode(string $code): ?Task
    {
        return $this->baseQuery()->where('code', $code)->first();
    }

    /**
     * Query nền tảng dùng chung cho list/tree/find — eager-load
     * Task::WITH_PRESENT (đã gồm parent/manager/acceptedBy/taskScore) +
     * withCount('attachments') + withSum('worklogs','hours') để
     * TaskService::present() lấy được attachments_count/worklog_hours mà
     * không phải N+1 query riêng cho từng task.
     */
    private function baseQuery(): Builder
    {
        return Task::query()
            ->with(Task::WITH_PRESENT)
            ->withCount('attachments')
            ->withSum('worklogs', 'hours');
    }

    /** Field derived/chừa-chỗ KHÔNG nằm trong Task::$fillable — set qua forceFill. */
    private const GUARDED_KEYS = [
        'origin_department_id', // Task Delegation §6 — TaskService::bulkDelegate()
        'delegated_to_department_id',
        'delegated_to_employee_id',
        'delegation_status',
        'accepted_by', // derived — TaskService::applyAcceptedTracking() set
        'accepted_at', // derived — TaskService::applyAcceptedTracking() set
    ];

    public function create(array $data): Task
    {
        // origin_department_id / accepted_by / accepted_at / delegated_to_* /
        // delegation_status cố ý KHÔNG nằm trong Task::$fillable — tách riêng
        // để Service vẫn set được các field derived/chừa-chỗ mà không mở
        // fillable cho cả nhóm cột nhạy cảm đó qua mass assignment thường.
        $guarded = array_intersect_key($data, array_flip(self::GUARDED_KEYS));
        $fillableData = array_diff_key($data, $guarded);

        $task = Task::query()->create($fillableData);

        if ($guarded !== []) {
            $task->forceFill($guarded)->save();
        }

        return $task->fresh(Task::WITH_PRESENT);
    }

    public function update(Task $task, array $data): Task
    {
        $guarded = array_intersect_key($data, array_flip(self::GUARDED_KEYS));
        $fillableData = array_diff_key($data, $guarded);

        $task->update($fillableData);

        if ($guarded !== []) {
            $task->forceFill($guarded)->save();
        }

        return $task->fresh(Task::WITH_PRESENT);
    }

    public function syncPeople(Task $task, array $watcherIds, array $collaboratorIds): Task
    {
        $task->watchers()->sync(array_values(array_unique($watcherIds)));
        $task->collaborators()->sync(array_values(array_unique($collaboratorIds)));

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

        if (! empty($filters['manager_id'])) {
            $query->where('manager_id', (int) $filters['manager_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['progress_type'])) {
            $query->where('progress_type', $filters['progress_type']);
        }

        if (! empty($filters['is_overdue'])) {
            $this->scopeOverdue($query);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('start_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('end_date', '<=', $filters['date_to']);
        }

        // Lịch: mọi task chồng lên khoảng đang xem (kể cả kéo dài nhiều ngày).
        if (! empty($filters['overlap_from']) && ! empty($filters['overlap_to'])) {
            $from = $filters['overlap_from'];
            $to = $filters['overlap_to'];
            $query->where(function (Builder $q) {
                $q->whereNotNull('start_date')->orWhereNotNull('end_date');
            })->whereRaw('DATE(COALESCE(start_date, end_date)) <= ?', [$to])
                ->whereRaw('DATE(COALESCE(end_date, start_date)) >= ?', [$from]);
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

        if ($tab === 'overdue') {
            $this->scopeOverdue($query);
        }
    }

    /**
     * Công thức "quá hạn" — PHẢI khớp TaskService::computeOverdue() (is_overdue):
     * end_date đã qua hôm nay và trạng thái chưa hoàn thành/huỷ. Dùng chung
     * cho cả tab "Quá hạn" và filter is_overdue để không lặp 2 nơi khác công thức.
     */
    private function scopeOverdue(Builder $query): void
    {
        $query->whereDate('end_date', '<', now()->toDateString())
            ->whereNotIn('status', ['completed', 'cancelled']);
    }
}
