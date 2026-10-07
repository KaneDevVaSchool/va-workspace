<?php

namespace Modules\Project\App\Services;

use Illuminate\Support\Facades\DB;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Repositories\Contracts\DepartmentRepositoryInterface;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Repositories\Contracts\ProjectRepositoryInterface;

/**
 * Chuyển tham chiếu phòng ban dự án từ id org-unit cũ sang id danh mục HRM
 * (cùng company + mã phần mềm / external_code).
 */
class ProjectDepartmentCatalogRemapper
{
    public function __construct(
        private readonly DepartmentRepositoryInterface $departments,
        private readonly ProjectRepositoryInterface $projects,
    ) {}

    /**
     * @return array{mapped_pairs: int, projects_updated: int, pivot_rows: int, tasks_updated: int, scopes_updated: int}
     */
    public function remapAll(): array
    {
        $map = $this->buildIdMap();
        $projectsUpdated = 0;
        $pivotRows = 0;
        $tasksUpdated = 0;
        $scopesUpdated = 0;

        Project::query()->orderBy('id')->each(function (Project $project) use (
            $map,
            &$projectsUpdated,
            &$pivotRows,
            &$tasksUpdated,
            &$scopesUpdated,
        ): void {
            $changed = false;

            foreach (['owner_department_id', 'lead_department_id', 'executing_department_id'] as $column) {
                $current = $project->{$column};
                if ($current === null) {
                    continue;
                }
                $next = $this->translateId((int) $current, $map);
                if ($next !== null && $next !== (int) $current) {
                    $project->{$column} = $next;
                    $changed = true;
                }
            }

            if ($changed) {
                $project->save();
                $projectsUpdated++;
            }

            $execIds = $project->executingDepartments()->pluck('departments.id')->map(fn ($id) => (int) $id)->all();
            $newExecIds = $this->translateIds($execIds, $map);
            if ($newExecIds === [] && $execIds !== []) {
                foreach (['lead_department_id', 'owner_department_id', 'executing_department_id'] as $column) {
                    $candidate = $project->{$column};
                    if ($candidate === null) {
                        continue;
                    }
                    $translated = $this->translateId((int) $candidate, $map);
                    if ($translated !== null) {
                        $newExecIds = [$translated];
                        break;
                    }
                }
            }
            if ($newExecIds !== $execIds) {
                $this->projects->replaceExecutingDepartments($project, $newExecIds);
                $pivotRows++;
            }

            $staleIds = array_values(array_diff($execIds, $newExecIds));
            $targetIds = $newExecIds !== [] ? $newExecIds : array_filter([
                $project->executing_department_id,
                $project->lead_department_id,
                $project->owner_department_id,
            ]);

            if ($staleIds !== [] && $targetIds !== []) {
                $fallback = (int) reset($targetIds);
                $updated = DB::table('tasks')
                    ->where('project_id', $project->id)
                    ->whereIn('origin_department_id', $staleIds)
                    ->update(['origin_department_id' => $fallback]);
                $tasksUpdated += $updated;

                $updated = DB::table('tasks')
                    ->where('project_id', $project->id)
                    ->whereIn('delegated_to_department_id', $staleIds)
                    ->update(['delegated_to_department_id' => $fallback]);
                $tasksUpdated += $updated;
            }

            if ($staleIds !== [] && DB::getSchemaBuilder()->hasTable('project_scopes')) {
                $fallback = $targetIds !== [] ? (int) reset($targetIds) : null;
                if ($fallback !== null) {
                    $scopesUpdated += DB::table('project_scopes')
                        ->where('project_id', $project->id)
                        ->whereIn('department_id', $staleIds)
                        ->update(['department_id' => $fallback]);
                }
            }
        });

        return [
            'mapped_pairs' => count($map),
            'projects_updated' => $projectsUpdated,
            'pivot_rows' => $pivotRows,
            'tasks_updated' => $tasksUpdated,
            'scopes_updated' => $scopesUpdated,
        ];
    }

    /**
     * @return array<int, int> old_id => active catalog id
     */
    public function buildIdMap(): array
    {
        $active = Department::query()
            ->where('is_active', true)
            ->whereNotNull('hrm_org_unit_uuid')
            ->get();

        /** @var array<string, Department> $bestActiveByKey */
        $bestActiveByKey = [];
        foreach ($active as $department) {
            $externalCode = strtolower(trim((string) ($department->external_code ?? '')));
            if ($externalCode === '') {
                continue;
            }
            $key = ($department->company_id ?? 0)."\0".$externalCode;
            if (! isset($bestActiveByKey[$key]) || $department->id > $bestActiveByKey[$key]->id) {
                $bestActiveByKey[$key] = $department;
            }
        }

        $activeIds = $active->pluck('id')->flip()->all();
        $map = [];

        Department::query()
            ->whereNotNull('hrm_org_unit_uuid')
            ->orderBy('id')
            ->each(function (Department $department) use (&$map, $activeIds, $bestActiveByKey): void {
                $id = (int) $department->id;
                if (isset($activeIds[$id])) {
                    $map[$id] = $id;

                    return;
                }

                $externalCode = strtolower(trim((string) ($department->external_code ?? '')));
                if ($externalCode !== '') {
                    $key = ($department->company_id ?? 0)."\0".$externalCode;
                    if (isset($bestActiveByKey[$key])) {
                        $map[$id] = (int) $bestActiveByKey[$key]->id;

                        return;
                    }
                }

                $byUuid = Department::query()
                    ->where('hrm_org_unit_uuid', $department->hrm_org_unit_uuid)
                    ->where('is_active', true)
                    ->orderByDesc('id')
                    ->value('id');
                if ($byUuid !== null) {
                    $map[$id] = (int) $byUuid;
                }
            });

        return $map;
    }

    /**
     * @param  array<int, int>  $map
     */
    private function translateId(int $departmentId, array $map): ?int
    {
        if (isset($map[$departmentId])) {
            return $map[$departmentId];
        }

        $department = Department::query()->find($departmentId);
        if ($department === null || ! $department->is_active) {
            return null;
        }

        return $departmentId;
    }

    /**
     * @param  list<int>  $ids
     * @param  array<int, int>  $map
     * @return list<int>
     */
    private function translateIds(array $ids, array $map): array
    {
        $translated = [];
        foreach ($ids as $id) {
            $next = $this->translateId((int) $id, $map);
            if ($next !== null) {
                $translated[] = $next;
            }
        }

        return $this->departments->collapseDuplicatePickerIds($translated);
    }
}
