<?php

namespace Modules\Dashboard\App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Dashboard\App\Repositories\Contracts\DepartmentDashboardRepositoryInterface;
use Modules\Identity\App\Models\Department;
use Modules\Project\App\Enums\TaskEnums;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\Task;

/**
 * Tầng duy nhất gọi Eloquent trực tiếp cho Dashboard phòng ban. Chỉ đọc.
 */
class DepartmentDashboardRepository implements DepartmentDashboardRepositoryInterface
{
    public function departmentName(int $departmentId): ?string
    {
        return Department::query()->whereKey($departmentId)->value('name');
    }

    public function projectIdsForDepartment(int $departmentId, ?int $teamId = null): array
    {
        // Phạm vi phòng ban không lọc theo team (Project không có cột team) —
        // team_id chỉ dùng để lọc nhân viên bên dưới, không thu hẹp tập dự án.
        return Project::query()
            ->where(function (Builder $q) use ($departmentId) {
                $q->where('owner_department_id', $departmentId)
                    ->orWhere('executing_department_id', $departmentId)
                    ->orWhereHas('executingDepartments', fn ($eq) => $eq->where('departments.id', $departmentId));
            })
            ->pluck('id')
            ->all();
    }

    public function userIdsForDepartment(int $departmentId, ?int $teamId = null): Collection
    {
        $query = User::query()->where('department_id', $departmentId);
        if ($teamId !== null) {
            $query->where('team_id', $teamId);
        }

        return $query->pluck('id');
    }

    public function counts(array $projectIds, Collection $userIds): array
    {
        $taskQuery = Task::query()->where('type', 'task');
        if ($userIds->isNotEmpty()) {
            $taskQuery->whereIn('assignee_id', $userIds);
        } else {
            $taskQuery->whereRaw('1 = 0');
        }

        return [
            'total_projects' => count($projectIds),
            'total_tasks' => (clone $taskQuery)->count(),
        ];
    }

    public function tasksByStatus(array $projectIds, Collection $userIds): array
    {
        $result = [];
        foreach (TaskEnums::STATUSES as $status) {
            $result[$status] = 0;
        }

        if ($userIds->isEmpty()) {
            return $result;
        }

        $rows = Task::query()
            ->where('type', 'task')
            ->whereIn('assignee_id', $userIds)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        foreach (TaskEnums::STATUSES as $status) {
            $result[$status] = (int) ($rows[$status] ?? 0);
        }

        return $result;
    }

    public function tasksForCalculation(array $projectIds, Collection $userIds): Collection
    {
        if ($userIds->isEmpty()) {
            return collect();
        }

        return Task::query()
            ->where('type', 'task')
            ->whereIn('assignee_id', $userIds)
            ->select(['id', 'assignee_id', 'status', 'progress_percent', 'start_date', 'end_date', 'created_at'])
            ->get();
    }

    public function paginateEmployees(int $departmentId, ?int $teamId, array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $query = User::query()
            ->where('department_id', $departmentId)
            ->with('team:id,name');

        if ($teamId !== null) {
            $query->where('team_id', $teamId);
        }

        if (! empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            if ($q !== '') {
                $query->where('name', 'like', "%{$q}%");
            }
        }

        if (! empty($filters['status'])) {
            // User model không khai báo quan hệ tasks() (Task thuộc module
            // Project) — lọc bằng whereIn(subquery) thay vì whereHas, tránh
            // phải thêm quan hệ chéo module vào User.
            $status = $filters['status'];
            $query->whereIn('id', function ($sub) use ($status) {
                $sub->select('assignee_id')
                    ->from('tasks')
                    ->where('type', 'task')
                    ->where('status', $status)
                    ->whereNotNull('assignee_id');
            });
        }

        $query->orderBy('name');

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function findUser(int $userId): ?User
    {
        return User::query()->with('team:id,name', 'department:id,name')->find($userId);
    }

    public function tasksForUser(int $userId, array $allowedProjectIds): Collection
    {
        return Task::query()
            ->where('type', 'task')
            ->where('assignee_id', $userId)
            ->with('project:id,name')
            ->orderByDesc('updated_at')
            ->get();
    }
}
