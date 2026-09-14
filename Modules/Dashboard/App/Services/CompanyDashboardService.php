<?php

namespace Modules\Dashboard\App\Services;

use Illuminate\Support\Carbon;
use Modules\Dashboard\App\Repositories\Contracts\CompanyDashboardRepositoryInterface;

/**
 * Business logic Dashboard tổng công ty — Controller chỉ gọi qua đây,
 * không đụng Eloquent trực tiếp (Repository là tầng duy nhất làm việc đó).
 */
class CompanyDashboardService
{
    public function __construct(
        private readonly CompanyDashboardRepositoryInterface $repository,
        private readonly ProjectProgressCalculator $progressCalculator,
        private readonly ProjectHealthClassifier $healthClassifier,
    ) {}

    public function overview(array $filters): array
    {
        $counts = $this->repository->counts($filters);
        $statusBreakdown = $this->repository->projectsByStatus($filters);

        $projects = $this->repository->projectsForCalculation($filters);
        $progressByProject = $this->progressCalculator->resolveMany($projects);

        // Đếm task lá cho mỗi project — cần cho Project Health (project chưa
        // có task nào dù đã qua start_date = Nguy cơ). Dùng lại dữ liệu đã
        // tổng hợp trong resolveMany() thay vì query lại — forProjects() trả
        // task_count kèm theo, nhưng resolveMany() chỉ trả progress; lấy lại
        // 1 lần ở đây cho rõ ràng (vẫn 1 query duy nhất, không N+1).
        $rawRows = $this->progressCalculator->forProjects($projects->pluck('id')->all());

        $health = ['good' => 0, 'warning' => 0, 'risk' => 0];
        $overdueAging = ['0_3' => 0, '4_7' => 0, '8_14' => 0, 'over_14' => 0];
        $overdueCount = 0;
        $progressValues = [];

        foreach ($projects as $project) {
            $taskCount = (int) ($rawRows[$project->id]->task_count ?? 0);
            $progress = $progressByProject[$project->id] ?? null;
            if ($progress !== null) {
                $progressValues[] = $progress;
            }

            $status = $this->healthClassifier->classify($project, $taskCount, $progress);
            $health[$status]++;

            $daysOverdue = $this->healthClassifier->daysOverdue($project);
            if ($daysOverdue > 0) {
                $overdueCount++;
                $overdueAging[$this->healthClassifier->agingBucket($daysOverdue)]++;
            }
        }

        $averageProgress = count($progressValues) > 0
            ? round(array_sum($progressValues) / count($progressValues), 1)
            : null;

        $departments = $this->repository->departmentsRaw($filters);
        $departmentsPerformance = $departments->map(function (array $dept) use ($progressByProject) {
            $deptProgress = collect($dept['project_ids'])
                ->map(fn ($id) => $progressByProject[$id] ?? null)
                ->filter(fn ($v) => $v !== null);

            return [
                'department_id' => $dept['department_id'],
                'department_name' => $dept['department_name'],
                'projects_running' => $dept['projects_running'],
                'projects_overdue' => $dept['projects_overdue'],
                'projects_completed' => $dept['projects_completed'],
                'average_progress_percent' => $deptProgress->isEmpty() ? null : round($deptProgress->avg(), 1),
            ];
        })
            ->sortBy([
                ['projects_overdue', 'desc'],
                ['average_progress_percent', 'asc'],
            ])
            ->values();

        return [
            'kpis' => [
                'total_projects' => $counts['total_projects'],
                'total_tasks' => $counts['total_tasks'],
                'average_progress_percent' => $averageProgress,
                'overdue_projects_count' => $overdueCount,
            ],
            'status_breakdown' => $statusBreakdown,
            'health' => $health,
            'overdue_aging' => $overdueAging,
            'departments_performance' => $departmentsPerformance,
            'timeline' => $this->buildTimeline($filters),
            'generated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * health/overdue_bucket không phải cột DB (tính runtime từ tiến độ +
     * trạng thái) — lấy toàn bộ project khớp filter cơ bản (status/
     * department/q/sort), tính health/aging cho cả tập rồi mới lọc + tự cắt
     * trang ở đây. Chấp nhận được vì đây là dữ liệu 1 công ty, không phải
     * triệu bản ghi; tránh N+1 vì tiến độ vẫn tính hàng loạt 1 query.
     */
    public function projects(array $filters, int $perPage, int $page): array
    {
        $all = $this->repository->listProjectsForTable($filters);
        $progressByProject = $this->progressCalculator->resolveMany($all);
        $rawRows = $this->progressCalculator->forProjects($all->pluck('id')->all());
        $taskCounts = $this->repository->taskCountsForProjects($all->pluck('id')->all());

        $rows = $all->map(function ($project) use ($progressByProject, $rawRows, $taskCounts) {
            $progress = $progressByProject[$project->id] ?? null;
            $taskCount = (int) ($rawRows[$project->id]->task_count ?? 0);
            $counts = $taskCounts[$project->id] ?? ['tasks_total' => 0, 'tasks_completed' => 0];

            return [
                'id' => $project->id,
                'code' => $project->code,
                'name' => $project->name,
                'department_name' => $project->ownerDepartment->name ?? '—',
                'status' => $project->status,
                'status_label' => \Modules\Project\App\Enums\ProjectEnums::STATUS_LABELS[$project->status] ?? $project->status,
                'progress_percent' => $progress,
                'end_date' => optional($project->end_date)->toDateString(),
                'days_overdue' => $this->healthClassifier->daysOverdue($project),
                'health' => $this->healthClassifier->classify($project, $taskCount, $progress),
                'lead_name' => $project->lead->name ?? null,
                'tasks_total' => $counts['tasks_total'],
                'tasks_completed' => $counts['tasks_completed'],
            ];
        });

        if (! empty($filters['health'])) {
            $rows = $rows->where('health', $filters['health']);
        }

        if (! empty($filters['overdue_bucket'])) {
            $bucket = $filters['overdue_bucket'];
            $rows = $rows->filter(fn ($row) => $row['days_overdue'] > 0 && $this->healthClassifier->agingBucket($row['days_overdue']) === $bucket);
        } elseif (! empty($filters['overdue_only'])) {
            // Click nhanh KPI "Dự án đang trễ hạn" — không phân biệt mức độ.
            $rows = $rows->filter(fn ($row) => $row['days_overdue'] > 0);
        }

        $rows = $rows->values();
        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $paged = $rows->forPage($page, $perPage)->values();

        return [
            'data' => $paged,
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    public function projectDetail(int $projectId): ?array
    {
        $project = $this->repository->findProject($projectId);
        if ($project === null) {
            return null;
        }

        $progressByProject = $this->progressCalculator->resolveMany(collect([$project]));
        $rawRows = $this->progressCalculator->forProjects([$project->id]);
        $taskCount = (int) ($rawRows[$project->id]->task_count ?? 0);
        $progress = $progressByProject[$project->id] ?? null;
        $taskCounts = $this->repository->taskCountsForProject($project->id);

        return [
            'id' => $project->id,
            'code' => $project->code,
            'name' => $project->name,
            'status' => $project->status,
            'status_label' => \Modules\Project\App\Enums\ProjectEnums::STATUS_LABELS[$project->status] ?? $project->status,
            'progress_percent' => $progress,
            'start_date' => optional($project->start_date)->toDateString(),
            'end_date' => optional($project->end_date)->toDateString(),
            'days_overdue' => $this->healthClassifier->daysOverdue($project),
            'health' => $this->healthClassifier->classify($project, $taskCount, $progress),
            'owner_department_name' => $project->ownerDepartment->name ?? null,
            'executing_departments' => $project->executingDepartments->pluck('name')->values(),
            'lead_name' => $project->lead->name ?? null,
            'tasks_total' => $taskCounts['tasks_total'],
            'tasks_completed' => $taskCounts['tasks_completed'],
        ];
    }

    private function buildTimeline(array $filters): array
    {
        $monthsBefore = (int) ($filters['months_before'] ?? 3);
        $monthsAfter = (int) ($filters['months_after'] ?? 6);

        $projectTimeline = $this->repository->timeline($filters, $monthsBefore, $monthsAfter);
        $taskTimeline = $this->repository->taskTimeline($filters, $monthsBefore, $monthsAfter);

        return [
            'months' => $projectTimeline['months'],
            'projects_starting' => $projectTimeline['starting'],
            'projects_ending' => $projectTimeline['ending'],
            'tasks_starting' => $taskTimeline['starting'],
            'tasks_ending' => $taskTimeline['ending'],
        ];
    }
}
