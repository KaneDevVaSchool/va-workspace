<?php

namespace Modules\Project\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Project\App\Models\Sprint;
use Modules\Project\App\Repositories\Contracts\SprintRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent trực tiếp cho entity Sprint.
 */
class SprintRepository implements SprintRepositoryInterface
{
    public function listForProject(int $projectId): Collection
    {
        return Sprint::query()
            ->with(['phase'])
            ->where('project_id', $projectId)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();
    }

    public function listForPhase(int $phaseId): Collection
    {
        return Sprint::query()
            ->with(['phase'])
            ->where('phase_id', $phaseId)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();
    }

    public function find(int $id): ?Sprint
    {
        return Sprint::query()->with(['phase'])->find($id);
    }

    public function create(array $data): Sprint
    {
        $sprint = Sprint::query()->create($data);

        return $sprint->fresh(['phase']);
    }

    public function update(Sprint $sprint, array $data): Sprint
    {
        $sprint->fill($data);
        $sprint->save();

        return $sprint->fresh(['phase']);
    }

    public function delete(Sprint $sprint): bool
    {
        return (bool) $sprint->delete();
    }
}
