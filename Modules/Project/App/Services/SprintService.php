<?php

namespace Modules\Project\App\Services;

use App\Models\User;
use Carbon\Carbon;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\Sprint;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Repositories\Contracts\ProjectRepositoryInterface;
use Modules\Project\App\Repositories\Contracts\SprintRepositoryInterface;
use Modules\Project\App\Repositories\Contracts\TaskRepositoryInterface;

/**
 * Business logic của Sprint — 1 Phase (Task type=phase) có nhiều Sprint.
 */
class SprintService
{
    public function __construct(
        private readonly SprintRepositoryInterface $sprints,
        private readonly ProjectRepositoryInterface $projects,
        private readonly TaskRepositoryInterface $tasks,
    ) {}

    public function listForProject(Project $project): array
    {
        return $this->sprints->listForProject($project->id)
            ->map(fn (Sprint $sprint) => $this->present($sprint))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Sprint|array{error: string}
     */
    public function create(Project $project, array $data, User $actor): Sprint|array
    {
        if (! $this->projects->viewerCanAssignTo($actor, $project)) {
            return ['error' => 'Bạn không thể tạo sprint trong dự án này.'];
        }

        $phase = $this->tasks->find((int) $data['phase_id']);
        if ($phase === null || $phase->project_id !== $project->id || $phase->type !== 'phase') {
            return ['error' => 'Giai đoạn không hợp lệ.'];
        }

        $dateError = $this->validateAgainstPhaseDates($phase, $data);
        if ($dateError !== null) {
            return ['error' => $dateError];
        }

        return $this->sprints->create([
            'project_id' => $project->id,
            'phase_id' => $phase->id,
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'planned',
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Sprint|array{error: string}
     */
    public function update(Sprint $sprint, array $data, User $actor): Sprint|array
    {
        $payload = ['updated_by' => $actor->id];

        $phase = $sprint->phase;
        if (array_key_exists('phase_id', $data) && (int) $data['phase_id'] !== $sprint->phase_id) {
            $phase = $this->tasks->find((int) $data['phase_id']);
            if ($phase === null || $phase->project_id !== $sprint->project_id || $phase->type !== 'phase') {
                return ['error' => 'Giai đoạn không hợp lệ.'];
            }
            $payload['phase_id'] = $phase->id;
        }

        foreach (['name', 'description', 'status', 'start_date', 'end_date', 'sort_order'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $field === 'name' ? trim($data[$field]) : $data[$field];
            }
        }

        $dateError = $this->validateAgainstPhaseDates($phase, array_merge([
            'start_date' => $sprint->start_date?->toDateString(),
            'end_date' => $sprint->end_date?->toDateString(),
        ], $payload));
        if ($dateError !== null) {
            return ['error' => $dateError];
        }

        return $this->sprints->update($sprint, $payload);
    }

    /** @return true|array{error: string} */
    public function delete(Sprint $sprint): bool|array
    {
        if ($sprint->tasks()->exists()) {
            return ['error' => 'Không thể xoá sprint đang có công việc — hãy gỡ công việc khỏi sprint này trước.'];
        }

        return $this->sprints->delete($sprint);
    }

    /** Ngày sprint phải nằm trong khoảng ngày của phase chứa nó (nếu phase có ngày). */
    private function validateAgainstPhaseDates(?Task $phase, array $data): ?string
    {
        if ($phase === null || $phase->start_date === null && $phase->end_date === null) {
            return null;
        }

        $start = ! empty($data['start_date']) ? Carbon::parse($data['start_date'])->startOfDay() : null;
        $end = ! empty($data['end_date']) ? Carbon::parse($data['end_date'])->startOfDay() : null;

        if ($start !== null && $phase->start_date !== null && $start->lt($phase->start_date)) {
            return 'Ngày bắt đầu sprint phải nằm trong thời gian của giai đoạn.';
        }
        if ($end !== null && $phase->end_date !== null && $end->gt($phase->end_date)) {
            return 'Ngày kết thúc sprint phải nằm trong thời gian của giai đoạn.';
        }

        return null;
    }

    public function present(Sprint $sprint): array
    {
        $withProgress = $sprint->relationLoaded('tasks')
            ? $sprint->tasks->filter(fn ($task) => $task->progress_percent !== null)
            : collect();

        return [
            'id' => $sprint->id,
            'project_id' => $sprint->project_id,
            'phase_id' => $sprint->phase_id,
            'phase' => $sprint->relationLoaded('phase') && $sprint->phase !== null ? [
                'id' => $sprint->phase->id,
                'title' => $sprint->phase->title,
                'code' => $sprint->phase->code,
            ] : null,
            'code' => $sprint->code,
            'name' => $sprint->name,
            'description' => $sprint->description,
            'status' => $sprint->status,
            'start_date' => $sprint->start_date?->toDateString(),
            'end_date' => $sprint->end_date?->toDateString(),
            'sort_order' => $sprint->sort_order,
            'task_count' => $sprint->relationLoaded('tasks') ? $sprint->tasks->count() : null,
            'avg_progress' => $withProgress->isNotEmpty()
                ? (int) round($withProgress->avg('progress_percent'))
                : null,
            'created_at' => $sprint->created_at?->toIso8601String(),
            'updated_at' => $sprint->updated_at?->toIso8601String(),
        ];
    }
}
