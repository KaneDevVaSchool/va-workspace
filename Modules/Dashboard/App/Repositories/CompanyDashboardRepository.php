<?php

namespace Modules\Dashboard\App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Dashboard\App\Repositories\Contracts\CompanyDashboardRepositoryInterface;
use Modules\Identity\App\Models\Department;
use Modules\Project\App\Enums\ProjectEnums;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\Task;

/**
 * Tầng duy nhất gọi Eloquent trực tiếp cho Dashboard tổng công ty. Chỉ đọc —
 * không có endpoint ghi trong module Dashboard.
 */
class CompanyDashboardRepository implements CompanyDashboardRepositoryInterface
{
    public function counts(array $filters): array
    {
        $projectIds = $this->baseProjectQuery($filters)->pluck('id');

        return [
            'total_projects' => $projectIds->count(),
            'total_tasks' => $projectIds->isEmpty()
                ? 0
                : Task::query()->whereIn('project_id', $projectIds)->where('type', 'task')->count(),
        ];
    }

    public function projectsByStatus(array $filters): array
    {
        $rows = $this->baseProjectQuery($filters)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $result = [];
        foreach (ProjectEnums::STATUSES as $status) {
            $result[$status] = (int) ($rows[$status] ?? 0);
        }

        return $result;
    }

    public function projectsForCalculation(array $filters): Collection
    {
        return $this->baseProjectQuery($filters)
            ->select(['id', 'status', 'start_date', 'end_date', 'progress_method', 'updated_at'])
            ->get();
    }

    public function departmentsRaw(array $filters): Collection
    {
        // owner_department_id, executing_department_id (cột đơn) + pivot N-N
        // project_executing_departments — 1 project có thể thuộc nhiều
        // phòng ban thực hiện, gộp lại theo department_id.
        $projects = $this->baseProjectQuery($filters)
            ->with('executingDepartments:id')
            ->select(['id', 'owner_department_id', 'executing_department_id', 'status', 'end_date'])
            ->get();

        $byDepartment = [];
        foreach ($projects as $project) {
            $departmentIds = collect([$project->owner_department_id, $project->executing_department_id])
                ->merge($project->executingDepartments->pluck('id'))
                ->filter()
                ->unique();

            foreach ($departmentIds as $departmentId) {
                $byDepartment[$departmentId] ??= ['project_ids' => [], 'projects' => collect()];
                $byDepartment[$departmentId]['project_ids'][] = $project->id;
                $byDepartment[$departmentId]['projects']->push($project);
            }
        }

        if (empty($byDepartment)) {
            return collect();
        }

        $departments = Department::query()
            ->whereIn('id', array_keys($byDepartment))
            ->pluck('name', 'id');

        return collect($byDepartment)->map(function (array $data, int $departmentId) use ($departments) {
            $projects = $data['projects'];
            $today = Carbon::today();

            return [
                'department_id' => $departmentId,
                'department_name' => $departments[$departmentId] ?? '—',
                'project_ids' => $data['project_ids'],
                'projects_running' => $projects->where('status', 'in_progress')->count(),
                'projects_completed' => $projects->where('status', 'completed')->count(),
                'projects_overdue' => $projects->filter(fn ($p) => $this->isOverdue($p, $today))->count(),
            ];
        })->values();
    }

    public function timeline(array $filters, int $monthsBefore, int $monthsAfter): array
    {
        $months = $this->monthRange($monthsBefore, $monthsAfter);
        $query = $this->baseProjectQuery($filters);

        $starting = (clone $query)->whereNotNull('start_date')
            ->select(DB::raw("DATE_FORMAT(start_date, '%Y-%m') as ym"), DB::raw('COUNT(*) as total'))
            ->groupBy('ym')->pluck('total', 'ym');

        $ending = (clone $query)->whereNotNull('end_date')
            ->select(DB::raw("DATE_FORMAT(end_date, '%Y-%m') as ym"), DB::raw('COUNT(*) as total'))
            ->groupBy('ym')->pluck('total', 'ym');

        return [
            'months' => $months,
            'starting' => array_map(fn ($m) => (int) ($starting[$m] ?? 0), $months),
            'ending' => array_map(fn ($m) => (int) ($ending[$m] ?? 0), $months),
        ];
    }

    public function taskTimeline(array $filters, int $monthsBefore, int $monthsAfter): array
    {
        $months = $this->monthRange($monthsBefore, $monthsAfter);
        $projectIds = $this->baseProjectQuery($filters)->pluck('id');

        if ($projectIds->isEmpty()) {
            return [
                'months' => $months,
                'starting' => array_fill(0, count($months), 0),
                'ending' => array_fill(0, count($months), 0),
            ];
        }

        $base = Task::query()->whereIn('project_id', $projectIds)->where('type', 'task');

        $starting = (clone $base)->whereNotNull('start_date')
            ->select(DB::raw("DATE_FORMAT(start_date, '%Y-%m') as ym"), DB::raw('COUNT(*) as total'))
            ->groupBy('ym')->pluck('total', 'ym');

        $ending = (clone $base)->whereNotNull('end_date')
            ->select(DB::raw("DATE_FORMAT(end_date, '%Y-%m') as ym"), DB::raw('COUNT(*) as total'))
            ->groupBy('ym')->pluck('total', 'ym');

        return [
            'months' => $months,
            'starting' => array_map(fn ($m) => (int) ($starting[$m] ?? 0), $months),
            'ending' => array_map(fn ($m) => (int) ($ending[$m] ?? 0), $months),
        ];
    }

    public function listProjectsForTable(array $filters): Collection
    {
        $query = $this->baseProjectQuery($filters)
            ->with(['ownerDepartment:id,name', 'lead:id,name']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['department_id'])) {
            $deptId = (int) $filters['department_id'];
            $query->where(function (Builder $q) use ($deptId) {
                $q->where('owner_department_id', $deptId)
                    ->orWhere('executing_department_id', $deptId)
                    ->orWhereHas('executingDepartments', fn ($eq) => $eq->where('departments.id', $deptId));
            });
        }

        if (! empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            if ($q !== '') {
                $query->where(function (Builder $sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%");
                });
            }
        }

        $sortColumn = match ($filters['sort_by'] ?? null) {
            'end_date' => 'end_date',
            'status' => 'status',
            'name' => 'name',
            default => 'end_date',
        };
        $sortDir = ($filters['sort_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortColumn, $sortDir)->orderBy('id')->get();
    }

    public function findProject(int $projectId): ?Project
    {
        return Project::query()
            ->with(['ownerDepartment:id,name', 'executingDepartments:id,name', 'lead:id,name'])
            ->find($projectId);
    }

    public function taskCountsForProject(int $projectId): array
    {
        $row = Task::query()
            ->where('project_id', $projectId)
            ->where('type', 'task')
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"),
            ])
            ->first();

        return [
            'tasks_total' => (int) ($row->total ?? 0),
            'tasks_completed' => (int) ($row->completed ?? 0),
        ];
    }

    public function taskCountsForProjects(array $projectIds): array
    {
        if (empty($projectIds)) {
            return [];
        }

        $rows = Task::query()
            ->whereIn('project_id', $projectIds)
            ->where('type', 'task')
            ->select([
                'project_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"),
            ])
            ->groupBy('project_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->project_id] = [
                'tasks_total' => (int) $row->total,
                'tasks_completed' => (int) $row->completed,
            ];
        }

        return $result;
    }

    /** Query gốc: áp filter phòng ban/khoảng thời gian dùng chung mọi phương thức. */
    private function baseProjectQuery(array $filters): Builder
    {
        $query = Project::query();

        if (! empty($filters['department_id'])) {
            $deptId = (int) $filters['department_id'];
            $query->where(function (Builder $q) use ($deptId) {
                $q->where('owner_department_id', $deptId)
                    ->orWhere('executing_department_id', $deptId)
                    ->orWhereHas('executingDepartments', fn ($eq) => $eq->where('departments.id', $deptId));
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('start_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('end_date', '<=', $filters['date_to']);
        }

        return $query;
    }

    private function isOverdue(object $project, Carbon $today): bool
    {
        if (empty($project->end_date) || in_array($project->status, ['completed', 'cancelled'], true)) {
            return false;
        }

        return Carbon::parse($project->end_date)->lt($today);
    }

    /** @return list<string> danh sách "YYYY-MM" từ $monthsBefore tháng trước đến $monthsAfter tháng sau. */
    private function monthRange(int $monthsBefore, int $monthsAfter): array
    {
        $months = [];
        $cursor = Carbon::now()->startOfMonth()->subMonths($monthsBefore);
        $end = Carbon::now()->startOfMonth()->addMonths($monthsAfter);

        while ($cursor->lte($end)) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $months;
    }
}
