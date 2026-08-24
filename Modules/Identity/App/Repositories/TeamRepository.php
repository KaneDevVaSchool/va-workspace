<?php

namespace Modules\Identity\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Identity\App\Models\Team;
use Modules\Identity\App\Repositories\Contracts\TeamRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent (Team) trực tiếp.
 */
class TeamRepository implements TeamRepositoryInterface
{
    public function allByDepartment(int $departmentId): Collection
    {
        return Team::query()
            ->where('department_id', $departmentId)
            ->with('teamLead')
            ->orderBy('name')
            ->get();
    }

    public function departmentIdsWithTeams(array $departmentIds): array
    {
        if ($departmentIds === []) {
            return [];
        }

        return Team::query()
            ->whereIn('department_id', $departmentIds)
            ->distinct()
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function find(int $id): ?Team
    {
        return Team::query()->with(['department', 'teamLead'])->find($id);
    }

    public function create(array $data): Team
    {
        return Team::query()->create($data);
    }

    public function update(Team $team, array $data): Team
    {
        $team->fill($data)->save();

        return $team;
    }

    public function delete(Team $team): void
    {
        $team->delete();
    }
}
