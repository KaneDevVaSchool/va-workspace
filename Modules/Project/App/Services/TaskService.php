<?php

namespace Modules\Project\App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Services\PermissionService;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\Task;
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

        return $this->tasks->paginate($filters, $perPage, $page, $allowedProjectIds);
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

        $data['updated_by'] = $editor->id;

        return $this->tasks->update($task, $data);
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
        return [
            'id' => $task->id,
            'project_id' => $task->project_id,
            'project' => $task->relationLoaded('project') && $task->project !== null
                ? ['id' => $task->project->id, 'code' => $task->project->code, 'name' => $task->project->name]
                : null,
            'parent_id' => $task->parent_id,
            'code' => $task->code,
            'type' => $task->type,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'priority' => $task->priority,
            'start_date' => $task->start_date?->toDateString(),
            'end_date' => $task->end_date?->toDateString(),
            'actual_start_date' => $task->actual_start_date?->toDateString(),
            'actual_end_date' => $task->actual_end_date?->toDateString(),
            'assignee_id' => $task->assignee_id,
            'assignee' => $this->presentUser($task->relationLoaded('assignee') ? $task->assignee : null),
            'progress_percent' => $task->progress_percent,
            'sort_order' => $task->sort_order,
            'creator' => $this->presentUser($task->relationLoaded('creator') ? $task->creator : null),
            'created_by' => $task->created_by,
            'updated_by' => $task->updated_by,
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
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
