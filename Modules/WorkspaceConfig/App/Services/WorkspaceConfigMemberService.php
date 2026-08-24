<?php

namespace Modules\WorkspaceConfig\App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Identity\App\Models\Role;
use Modules\Identity\App\Models\Team;
use Modules\Identity\App\Repositories\Contracts\RoleRepositoryInterface;
use Modules\Identity\App\Repositories\Contracts\TeamRepositoryInterface;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;
use Modules\Identity\App\Services\TeamService;
use Modules\WorkspaceConfig\App\Exceptions\RoleNotAssignable;

/**
 * Thành viên + nhóm của phòng ban cho hub WorkspaceConfig — mỏng, gọi
 * Repository/Service của Identity (module này không có Repository riêng).
 */
class WorkspaceConfigMemberService
{
    /** Vai trò trưởng phòng được phép gán cho nhân sự trong phòng ban mình. */
    public const ASSIGNABLE_ROLE_CODES = [
        'deputy_department_director',
        'section_head',
        'team_lead',
        'member',
        'viewer',
    ];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly TeamRepositoryInterface $teams,
        private readonly TeamService $teamService,
        private readonly RoleRepositoryInterface $roles,
    ) {}

    public function forDepartment(int $departmentId): Collection
    {
        return $this->users->allByDepartment($departmentId)
            ->map(fn (User $user) => $this->presentMember($user))
            ->values();
    }

    public function presentMember(User $user): array
    {
        $user->loadMissing(['team', 'roles']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'status' => $user->status,
            'team' => $user->team ? [
                'id' => $user->team->id,
                'name' => $user->team->name,
            ] : null,
            'roles' => $user->roles
                ->map(fn ($role) => [
                    'code' => $role->code,
                    'name' => $role->name,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array{code: string, name: string, description: string|null}>
     */
    public function assignableRoles(): array
    {
        $order = array_flip(self::ASSIGNABLE_ROLE_CODES);

        return $this->roles->all()
            ->filter(fn (Role $role) => isset($order[$role->code]))
            ->sortBy(fn (Role $role) => $order[$role->code])
            ->map(fn (Role $role) => [
                'code' => $role->code,
                'name' => $role->name,
                'description' => $role->description,
            ])
            ->values()
            ->all();
    }

    /**
     * Gán đúng 1 vai trò phòng ban cho thành viên cùng phòng. Không được
     * đổi vai trò của chính mình hoặc tài khoản có role ngoài danh sách
     * ASSIGNABLE (super_admin, trưởng phòng, …).
     *
     * @throws RoleNotAssignable
     */
    public function assignRole(int $departmentId, int $actorId, int $userId, string $roleCode): User
    {
        if (! in_array($roleCode, self::ASSIGNABLE_ROLE_CODES, true)) {
            throw new RoleNotAssignable('Vai trò này không thể gán từ cấu hình phòng ban.');
        }

        if ($actorId === $userId) {
            throw new RoleNotAssignable('Không thể đổi vai trò của chính mình.');
        }

        $user = $this->users->findById($userId);

        if (! $user || (int) $user->department_id !== $departmentId) {
            throw new RoleNotAssignable('Thành viên không thuộc phòng ban này.');
        }

        if (! $user->isActive()) {
            throw new RoleNotAssignable('Chỉ gán vai trò cho thành viên đang hoạt động.');
        }

        $user->loadMissing('roles');
        $currentCodes = $user->roles->pluck('code')->all();
        $assignable = array_flip(self::ASSIGNABLE_ROLE_CODES);

        foreach ($currentCodes as $code) {
            if ($code !== '' && ! isset($assignable[$code])) {
                throw new RoleNotAssignable('Không thể đổi vai trò của tài khoản quản trị hoặc trưởng phòng.');
            }
        }

        $role = $this->roles->findByCode($roleCode);

        if ($role === null) {
            throw new RoleNotAssignable('Vai trò không tồn tại.');
        }

        $this->roles->syncForUser($user->id, [$role->id]);

        return $user->fresh(['team', 'roles']) ?? $user;
    }

    public function teamsForDepartment(int $departmentId): Collection
    {
        return $this->teams->allByDepartment($departmentId)
            ->map(fn (Team $team) => $this->presentTeam($team))
            ->values();
    }

    /**
     * @param  array{name: string, team_lead_id?: int|null}  $data
     */
    public function createTeam(int $departmentId, array $data): Team
    {
        $team = $this->teamService->create([
            'department_id' => $departmentId,
            'name' => $data['name'],
            'team_lead_id' => $data['team_lead_id'] ?? null,
        ]);

        return $team->loadMissing('teamLead');
    }

    /**
     * @param  array{name: string, team_lead_id?: int|null}  $data
     */
    public function updateTeam(int $departmentId, int $teamId, array $data): ?Team
    {
        $team = $this->teams->find($teamId);

        if ($team === null || (int) $team->department_id !== $departmentId) {
            return null;
        }

        $updated = $this->teamService->update($team, [
            'name' => $data['name'],
            'team_lead_id' => $data['team_lead_id'] ?? null,
        ]);

        return $updated->unsetRelation('teamLead')->load('teamLead');
    }

    public function presentTeam(Team $team): array
    {
        $team->loadMissing('teamLead');

        return [
            'id' => $team->id,
            'name' => $team->name,
            'team_lead_id' => $team->team_lead_id,
            'team_lead' => $team->teamLead ? [
                'id' => $team->teamLead->id,
                'name' => $team->teamLead->name,
            ] : null,
        ];
    }

    /**
     * Hàng bảng tổng hợp workspace — cùng shape khi sau này Department
     * repository đổi sang API HRM (director = trưởng đơn vị + email liên hệ).
     *
     * @param  Collection<int, \Modules\Identity\App\Models\Department>  $departments
     * @return Collection<int, array<string, mixed>>
     */
    public function overviewRows(Collection $departments): Collection
    {
        $ids = $departments->pluck('id')->all();
        $counts = $this->users->countByDepartmentIds($ids);
        $directors = $this->users->departmentDirectorsByDepartmentIds($ids);

        return $departments->map(fn ($department) => [
            'id' => $department->id,
            'code' => $department->code,
            'name' => $department->name,
            'member_count' => (int) $counts->get($department->id, 0),
            // Số tiêu chí đánh giá — giá trị thật khi Giai đoạn B (module Evaluation).
            'criteria_count' => 0,
            'director' => $this->presentDirector($directors->get($department->id)),
        ])->values();
    }

    public function directorForDepartment(int $departmentId): ?array
    {
        return $this->presentDirector(
            $this->users->departmentDirectorsByDepartmentIds([$departmentId])->get($departmentId),
        );
    }

    private function presentDirector(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
