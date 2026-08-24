<?php

namespace Modules\Identity\App\Services;

use Modules\Identity\App\Exceptions\TeamLeadNotInDepartment;
use Modules\Identity\App\Models\Team;
use Modules\Identity\App\Repositories\Contracts\TeamRepositoryInterface;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;

/**
 * CRUD Team (mỏng) + gán/đổi team_lead_id.
 *
 * KHÔNG giới hạn 1 user chỉ lead 1 team — đã xác nhận với người yêu cầu:
 * 1 user có thể là team_lead_id của nhiều team cùng lúc.
 */
class TeamService
{
    public function __construct(
        private readonly TeamRepositoryInterface $teams,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * @param  array{department_id: int, name: string, team_lead_id?: int|null}  $data
     */
    public function create(array $data): Team
    {
        $this->validateTeamLead($data['department_id'], $data['team_lead_id'] ?? null);

        return $this->teams->create($data);
    }

    /**
     * @param  array{department_id?: int, name?: string, team_lead_id?: int|null}  $data
     */
    public function update(Team $team, array $data): Team
    {
        $departmentId = $data['department_id'] ?? $team->department_id;

        if (array_key_exists('team_lead_id', $data)) {
            $this->validateTeamLead($departmentId, $data['team_lead_id']);
        }

        return $this->teams->update($team, $data);
    }

    public function delete(Team $team): void
    {
        $this->teams->delete($team);
    }

    /**
     * @throws TeamLeadNotInDepartment
     */
    private function validateTeamLead(int $departmentId, ?int $teamLeadId): void
    {
        if ($teamLeadId === null) {
            return;
        }

        $lead = $this->users->findById($teamLeadId);

        if (! $lead || (int) $lead->department_id !== $departmentId || ! $lead->isActive()) {
            throw new TeamLeadNotInDepartment();
        }
    }
}
