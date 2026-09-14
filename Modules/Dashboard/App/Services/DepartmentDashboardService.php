<?php

namespace Modules\Dashboard\App\Services;

use Illuminate\Support\Carbon;
use Modules\Dashboard\App\Repositories\Contracts\DepartmentDashboardRepositoryInterface;
use Modules\Project\App\Enums\TaskEnums;

/**
 * Business logic Dashboard phòng ban — Controller chỉ gọi qua đây.
 */
class DepartmentDashboardService
{
    public function __construct(
        private readonly DepartmentDashboardRepositoryInterface $repository,
        private readonly ProjectHealthClassifier $healthClassifier,
    ) {}

    public function overview(int $departmentId, ?int $teamId): array
    {
        $projectIds = $this->repository->projectIdsForDepartment($departmentId, $teamId);
        $userIds = $this->repository->userIdsForDepartment($departmentId, $teamId);

        $counts = $this->repository->counts($projectIds, $userIds);
        $tasksByStatus = $this->repository->tasksByStatus($projectIds, $userIds);
        $tasks = $this->repository->tasksForCalculation($projectIds, $userIds);

        $progressValues = [];
        $workAging = ['0_3' => 0, '4_7' => 0, '8_14' => 0, 'over_14' => 0];
        $overdueTasksCount = 0;
        $today = Carbon::today();

        $workloadByUser = [];
        $progressByUser = [];

        foreach ($tasks as $task) {
            if ($task->progress_percent !== null && $task->status !== 'cancelled') {
                $progressValues[] = (float) $task->progress_percent;
            }

            $workloadByUser[$task->assignee_id] ??= 0;
            $workloadByUser[$task->assignee_id]++;

            if ($task->progress_percent !== null && $task->status !== 'cancelled') {
                $progressByUser[$task->assignee_id]['sum'] = ($progressByUser[$task->assignee_id]['sum'] ?? 0) + (float) $task->progress_percent;
                $progressByUser[$task->assignee_id]['count'] = ($progressByUser[$task->assignee_id]['count'] ?? 0) + 1;
            }

            $isUnfinished = ! in_array($task->status, ['completed', 'cancelled'], true);
            if ($isUnfinished) {
                $referenceDate = $task->start_date ?? $task->created_at;
                $days = $referenceDate ? Carbon::parse($referenceDate)->diffInDays($today) : 0;
                $workAging[$this->healthClassifier->agingBucket($days)]++;

                if (! empty($task->end_date) && Carbon::parse($task->end_date)->lt($today)) {
                    $overdueTasksCount++;
                }
            }
        }

        $averageProgress = count($progressValues) > 0
            ? round(array_sum($progressValues) / count($progressValues), 1)
            : null;

        $userNames = $this->userNames(array_keys($workloadByUser));

        $workload = collect($workloadByUser)
            ->map(fn ($total, $userId) => [
                'user_id' => $userId,
                'name' => $userNames[$userId] ?? '—',
                'tasks_total' => $total,
            ])
            ->sortByDesc('tasks_total')
            ->values();

        $employeeProgress = collect($progressByUser)
            ->map(fn ($data, $userId) => [
                'user_id' => $userId,
                'name' => $userNames[$userId] ?? '—',
                'average_progress_percent' => round($data['sum'] / $data['count'], 1),
            ])
            ->sortByDesc('average_progress_percent')
            ->values();

        return [
            'department_id' => $departmentId,
            'department_name' => $this->repository->departmentName($departmentId) ?? '—',
            'kpis' => [
                'total_projects' => $counts['total_projects'],
                'total_tasks' => $counts['total_tasks'],
                'average_progress_percent' => $averageProgress,
                'overdue_tasks_count' => $overdueTasksCount,
            ],
            'tasks_status_breakdown' => $tasksByStatus,
            'workload' => $workload,
            'employee_progress' => $employeeProgress,
            'work_aging' => $workAging,
        ];
    }

    public function employees(int $departmentId, ?int $teamId, array $filters, int $perPage, int $page): array
    {
        $paginator = $this->repository->paginateEmployees($departmentId, $teamId, $filters, $perPage, $page);
        $projectIds = $this->repository->projectIdsForDepartment($departmentId, $teamId);

        $data = collect($paginator->items())->map(function ($user) use ($projectIds) {
            $tasks = $this->repository->tasksForUser($user->id, $projectIds);

            $tasksByStatus = [];
            foreach (TaskEnums::STATUSES as $status) {
                $tasksByStatus[$status] = 0;
            }

            $progressValues = [];
            $projects = [];

            foreach ($tasks as $task) {
                $tasksByStatus[$task->status] = ($tasksByStatus[$task->status] ?? 0) + 1;

                if ($task->progress_percent !== null && $task->status !== 'cancelled') {
                    $progressValues[] = (float) $task->progress_percent;
                }

                if ($task->project) {
                    $projects[$task->project->id] = $task->project->name;
                }
            }

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar_url,
                'team_name' => $user->team->name ?? null,
                'projects' => collect($projects)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
                'tasks_total' => $tasks->count(),
                'tasks_by_status' => $tasksByStatus,
                'average_progress_percent' => count($progressValues) > 0 ? round(array_sum($progressValues) / count($progressValues), 1) : null,
            ];
        })->values();

        return [
            'data' => $data,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    public function employeeDetail(int $userId, int $viewerDepartmentId): ?array
    {
        $user = $this->repository->findUser($userId);
        if ($user === null || (int) $user->department_id !== $viewerDepartmentId) {
            return null;
        }

        $projectIds = $this->repository->projectIdsForDepartment($viewerDepartmentId);
        $tasks = $this->repository->tasksForUser($userId, $projectIds);

        $projects = [];
        $taskList = [];

        foreach ($tasks as $task) {
            if ($task->project) {
                $projects[$task->project->id] = $task->project->name;
            }

            $taskList[] = [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'status_label' => TaskEnums::STATUS_LABELS[$task->status] ?? $task->status,
                'progress_percent' => $task->progress_percent,
                'project_name' => $task->project->name ?? null,
            ];
        }

        $progressValues = collect($tasks)
            ->filter(fn ($t) => $t->progress_percent !== null && $t->status !== 'cancelled')
            ->pluck('progress_percent');

        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatar_url,
            'team_name' => $user->team->name ?? null,
            'department_name' => $user->department->name ?? null,
            'projects' => collect($projects)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'tasks' => $taskList,
            'average_progress_percent' => $progressValues->isEmpty() ? null : round($progressValues->avg(), 1),
        ];
    }

    /** @return array<int, string> */
    private function userNames(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        return \App\Models\User::query()->whereIn('id', $userIds)->pluck('name', 'id')->all();
    }
}
