<?php

namespace Modules\Identity\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Identity\App\Models\Team;

/**
 * Team là domain data sở hữu lâu dài của Workspace (không phải dữ liệu tạm
 * chờ HRM) — nhưng vẫn tách interface theo đúng pattern Repository chung
 * của dự án (CLAUDE.md mục 5).
 */
interface TeamRepositoryInterface
{
    /** @return Collection<int, Team> */
    public function allByDepartment(int $departmentId): Collection;

    /**
     * Phòng ban nào đã có ít nhất một nhóm — dùng cho cờ "đã có cấu hình".
     *
     * @param  list<int>  $departmentIds
     * @return list<int>
     */
    public function departmentIdsWithTeams(array $departmentIds): array;

    public function find(int $id): ?Team;

    public function create(array $data): Team;

    public function update(Team $team, array $data): Team;

    public function delete(Team $team): void;
}
