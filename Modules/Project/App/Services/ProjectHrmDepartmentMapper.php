<?php

namespace Modules\Project\App\Services;

use App\Models\User;
use Modules\Identity\App\Hrm\Services\HrmDepartmentSyncService;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\Task;

/**
 * Gắn phòng ban sở hữu / phụ trách / thực hiện của dự án vào đúng phòng ban
 * HRM của người phụ trách (hoặc người tạo nếu chưa có người phụ trách).
 */
class ProjectHrmDepartmentMapper
{
    public function __construct(
        private readonly HrmDepartmentSyncService $departments,
    ) {}

    public function mapAll(): int
    {
        $updated = 0;

        Project::query()->orderBy('id')->each(function (Project $project) use (&$updated): void {
            if ($this->map($project)) {
                $updated++;
            }
        });

        return $updated;
    }

    public function map(Project $project): bool
    {
        $departmentId = $this->departmentIdFor($project);
        if ($departmentId === null) {
            return false;
        }

        $previousIds = array_values(array_unique(array_filter([
            $project->owner_department_id !== null ? (int) $project->owner_department_id : null,
            $project->lead_department_id !== null ? (int) $project->lead_department_id : null,
            $project->executing_department_id !== null ? (int) $project->executing_department_id : null,
        ])));

        $changed = (int) $project->owner_department_id !== $departmentId
            || (int) $project->lead_department_id !== $departmentId
            || (int) $project->executing_department_id !== $departmentId;

        if ($changed) {
            $project->forceFill([
                'owner_department_id' => $departmentId,
                'lead_department_id' => $departmentId,
                'executing_department_id' => $departmentId,
            ])->save();
        }

        $linked = $project->executingDepartments()->pluck('departments.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        if ($linked !== [$departmentId]) {
            $project->executingDepartments()->sync([$departmentId]);
            $changed = true;
        }

        $staleIds = array_values(array_filter(
            $previousIds,
            fn (int $id): bool => $id !== $departmentId,
        ));

        $tasksUpdated = Task::query()
            ->where('project_id', $project->id)
            ->where(function ($query) use ($staleIds): void {
                $query->whereNull('origin_department_id');
                if ($staleIds !== []) {
                    $query->orWhereIn('origin_department_id', $staleIds);
                }
            })
            ->update(['origin_department_id' => $departmentId]);
        if ($tasksUpdated > 0) {
            $changed = true;
        }

        return $changed;
    }

    private function departmentIdFor(Project $project): ?int
    {
        foreach ([$project->lead_user_id, $project->created_by] as $userId) {
            if ($userId === null) {
                continue;
            }

            $user = User::query()->find($userId);
            if ($user === null) {
                continue;
            }

            $departmentId = $this->departments->departmentIdMatchingHrm($user);
            if ($departmentId !== null) {
                return $departmentId;
            }
        }

        return null;
    }
}
