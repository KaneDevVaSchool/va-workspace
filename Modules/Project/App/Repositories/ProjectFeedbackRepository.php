<?php

namespace Modules\Project\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Project\App\Models\ProjectFeedback;
use Modules\Project\App\Repositories\Contracts\ProjectFeedbackRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent trực tiếp cho entity ProjectFeedback.
 */
class ProjectFeedbackRepository implements ProjectFeedbackRepositoryInterface
{
    public function listForProject(int $projectId): Collection
    {
        return ProjectFeedback::query()
            ->with('author')
            ->where('project_id', $projectId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function find(int $id): ?ProjectFeedback
    {
        return ProjectFeedback::query()->with('author')->find($id);
    }

    public function create(array $data): ProjectFeedback
    {
        $feedback = ProjectFeedback::query()->create($data);

        return $feedback->fresh('author');
    }

    public function update(ProjectFeedback $feedback, array $data): ProjectFeedback
    {
        $feedback->fill($data);
        $feedback->save();

        return $feedback->fresh('author');
    }

    public function delete(ProjectFeedback $feedback): bool
    {
        return (bool) $feedback->delete();
    }
}
