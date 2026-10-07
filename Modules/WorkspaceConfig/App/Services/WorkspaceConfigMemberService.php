<?php

namespace Modules\WorkspaceConfig\App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Identity\App\Hrm\Exceptions\HrmDatabaseUnavailable;
use Modules\Identity\App\Hrm\Services\HrmDepartmentSyncService;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\App\Models\Team;
use Modules\Identity\App\Repositories\Contracts\DepartmentRepositoryInterface;
use Modules\Identity\App\Repositories\Contracts\DepartmentSidebarConfigRepositoryInterface;
use Modules\Identity\App\Repositories\Contracts\RoleRepositoryInterface;
use Modules\Identity\App\Repositories\Contracts\TeamRepositoryInterface;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;
use Modules\Identity\App\Services\DefaultMemberRoleBootstrap;
use Modules\Identity\App\Services\TeamService;
use Modules\WorkspaceConfig\App\Exceptions\MemberDepartmentNotAssignable;
use Modules\WorkspaceConfig\App\Exceptions\MemberTeamNotAssignable;
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
        private readonly DepartmentSidebarConfigRepositoryInterface $sidebarConfigs,
        private readonly DepartmentRepositoryInterface $departments,
        private readonly DefaultMemberRoleBootstrap $defaultMemberRole,
        private readonly HrmEmployeeDirectory $hrmDirectory,
        private readonly HrmDepartmentSyncService $hrmDepartments,
    ) {}

    public function forDepartment(int $departmentId): Collection
    {
        return $this->users->allByDepartment($departmentId)
            ->map(fn (User $user) => $this->presentMember($user))
            ->values();
    }

    public function presentMember(User $user): array
    {
        $user->loadMissing(['department', 'team', 'roles', 'company', 'concurrentPositions']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'status' => $user->status,
            'department' => $user->department ? [
                'id' => $user->department->id,
                'name' => $user->department->name,
            ] : null,
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
            // Hồ sơ hiển thị đồng bộ từ VA-HRM — không dùng để phân quyền.
            'employee_code' => $user->employee_code,
            'job_title_name' => $user->job_title_name,
            'job_position_level' => $user->job_position_level,
            'company' => $user->company ? [
                'id' => $user->company->id,
                'code' => $user->company->code,
                'name' => $user->company->name,
            ] : null,
            'manager_display_name' => $user->manager_display_name,
            'concurrent_positions' => $user->concurrentPositions
                ->map(fn ($position) => [
                    'job_title_name' => $position->job_title_name,
                    'company_name' => $position->company_name,
                    'org_unit_name' => $position->org_unit_name,
                ])
                ->values()
                ->all(),
            'hrm_synced_at' => $user->hrm_synced_at?->toIso8601String(),
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

    /**
     * Gán hoặc bỏ nhóm cho thành viên cùng phòng ban (`users.team_id`).
     *
     * @throws MemberTeamNotAssignable
     */
    public function assignMemberTeam(int $departmentId, int $actorId, int $userId, ?int $teamId): User
    {
        if ($actorId === $userId) {
            throw new MemberTeamNotAssignable('Không thể đổi nhóm của chính mình.');
        }

        $user = $this->users->findById($userId);

        if (! $user || (int) $user->department_id !== $departmentId) {
            throw new MemberTeamNotAssignable('Thành viên không thuộc phòng ban này.');
        }

        if (! $user->isActive()) {
            throw new MemberTeamNotAssignable('Chỉ gán nhóm cho thành viên đang hoạt động.');
        }

        if ($teamId !== null) {
            $team = $this->teams->find($teamId);

            if ($team === null || (int) $team->department_id !== $departmentId) {
                throw new MemberTeamNotAssignable('Nhóm không thuộc phòng ban này.');
            }
        }

        $this->users->update($user, ['team_id' => $teamId]);

        return $user->fresh(['team', 'roles']) ?? $user;
    }

    /** Đồng bộ thành viên đã được chỉ định trưởng nhóm nhưng chưa có `users.team_id`. */
    public function syncDepartmentTeamLeadMemberships(int $departmentId): void
    {
        $this->teamService->syncDepartmentLeadMemberships($departmentId);
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
        $departments->loadMissing('company');

        $ids = $departments->pluck('id')->all();
        $counts = $this->users->countByDepartmentIds($ids);
        $directors = $this->users->departmentDirectorsByDepartmentIds($ids);
        $hrmConfigured = HrmDepartmentSyncService::isConfigured();
        $usersByHrmEmployeeUuid = $hrmConfigured
            ? User::query()
                ->whereIn(
                    'hrm_employee_uuid',
                    $departments->pluck('hrm_manager_employee_uuid')->filter()->unique()->values()->all(),
                )
                ->get()
                ->keyBy('hrm_employee_uuid')
            : collect();
        $configuredIds = array_flip([
            ...$this->teams->departmentIdsWithTeams($ids),
            ...$this->sidebarConfigs->departmentIdsWithConfig($ids),
        ]);

        return $departments->map(fn ($department) => [
            'id' => $department->id,
            'code' => $department->code,
            'name' => $department->name,
            'is_active' => (bool) $department->is_active,
            'company' => $this->presentCompany($department->company),
            // Đã tạo nhóm hoặc đã lưu override menu sidebar.
            'has_config' => isset($configuredIds[$department->id]),
            'member_count' => (int) $counts->get($department->id, 0),
            // Số tiêu chí đánh giá — giá trị thật khi Giai đoạn B (module Evaluation).
            'criteria_count' => 0,
            'director' => $this->presentDirectorForDepartment(
                $department,
                $directors->get($department->id),
                $usersByHrmEmployeeUuid,
            ),
        ])->values();
    }

    public function directorForDepartment(int $departmentId): ?array
    {
        $department = $this->departments->find($departmentId);
        if ($department === null) {
            return null;
        }

        $workspaceDirector = $this->users->departmentDirectorsByDepartmentIds([$departmentId])->get($departmentId);
        $usersByHrmEmployeeUuid = collect();
        if (HrmDepartmentSyncService::isConfigured() && filled($department->hrm_manager_employee_uuid)) {
            $linked = User::query()->where('hrm_employee_uuid', $department->hrm_manager_employee_uuid)->first();
            if ($linked !== null) {
                $usersByHrmEmployeeUuid = collect([$department->hrm_manager_employee_uuid => $linked]);
            }
        }

        return $this->presentDirectorForDepartment($department, $workspaceDirector, $usersByHrmEmployeeUuid);
    }

    /**
     * Tài khoản chưa gắn phòng ban nào — thường là mới đăng nhập Google lần
     * đầu, chờ super_admin gán tay cho tới khi có API HRM (mục "Nhân sự
     * chưa gán phòng ban" ở /superadmin/workspace-config).
     */
    public function unassignedMembers(): Collection
    {
        return $this->users->allUnassigned()
            ->map(fn (User $user) => $this->presentMember($user))
            ->values();
    }

    /**
     * Danh sách phẳng khi chưa cấu hình MySQL HRM — tài khoản workspace local.
     *
     * @return array<string, mixed>
     */
    public function workspaceRoster(): array
    {
        $departments = $this->departments->all();
        $unassigned = $this->unassignedMembers();
        $groups = $this->departmentRosterGroups($departments);
        $members = $unassigned->concat(
            $groups->flatMap(fn (array $group) => $group['members'])
        )->unique('id')->values();

        return $this->rosterPayload('workspace', $members, $unassigned, $groups, $departments);
    }

    /**
     * Nhân sự đọc từ MySQL VA-HRM, phủ phòng ban workspace nếu đã có tài khoản.
     * Không gọi API HRM. Không tự gán department_id.
     *
     * @return array<string, mixed>
     *
     * @throws HrmDatabaseUnavailable
     */
    public function rosterFromHrmDatabase(): array
    {
        $loaded = $this->hrmDirectory->load();
        $this->hrmDepartments->syncDepartmentsFromRecords($loaded['org_units']);

        $departments = $this->departments->all();
        $users = $this->usersForHrmEmployees($loaded['employees']);
        $members = collect($loaded['employees'])
            ->map(fn (array $employee) => $this->presentHrmEmployee(
                $employee,
                $this->matchWorkspaceUser($employee, $users),
            ))
            ->values();
        $unassigned = $members
            ->filter(fn (array $member) => $member['department'] === null)
            ->values();

        return $this->rosterPayload(
            'hrm_database',
            $members,
            $unassigned,
            $this->groupMembersByDepartment($departments, $members),
            $departments,
        );
    }

    /**
     * Tạo tài khoản workspace nếu chưa có, rồi gán phòng ban. Hồ sơ hiển thị
     * lấy từ dòng HRM vừa đọc, không gọi API.
     *
     * @return array<string, mixed>
     *
     * @throws MemberDepartmentNotAssignable
     * @throws HrmDatabaseUnavailable
     */
    public function assignDepartmentFromHrm(string $employeeUuid, int $departmentId): array
    {
        $employee = $this->hrmDirectory->findEmployee($employeeUuid);
        if ($employee === null) {
            throw new MemberDepartmentNotAssignable('Không tìm thấy nhân sự trên HRM.');
        }

        $email = $this->hrmEmail($employee);
        if ($email === null) {
            throw new MemberDepartmentNotAssignable('Nhân sự chưa có email nên chưa tạo được tài khoản workspace.');
        }

        $user = $this->users->findByHrmEmployeeUuid($employeeUuid)
            ?? $this->users->findByEmail($email);

        if ($user !== null && filled($user->hrm_employee_uuid) && $user->hrm_employee_uuid !== $employeeUuid) {
            throw new MemberDepartmentNotAssignable('Email này đã gắn với một nhân sự HRM khác.');
        }

        if ($user === null) {
            $active = ['active', 'processing', 'pending_confirmation', 'on_leave'];
            $user = $this->users->create([
                'name' => filled($employee['full_name']) ? $employee['full_name'] : $email,
                'email' => $email,
                'hrm_employee_uuid' => $employeeUuid,
                'employee_code' => $employee['code'],
                'job_title_name' => $employee['job_title'],
                'job_position_level' => $employee['level_name'],
                'manager_display_name' => $employee['manager_name'],
                'status' => in_array($employee['status'], $active, true) ? 'active' : 'inactive',
            ]);
        } else {
            $attributes = [
                'employee_code' => $employee['code'],
                'job_title_name' => $employee['job_title'],
                'job_position_level' => $employee['level_name'],
                'manager_display_name' => $employee['manager_name'],
            ];
            if (filled($employee['full_name'])) {
                $attributes['name'] = $employee['full_name'];
            }
            if ($user->hrm_employee_uuid === null) {
                $attributes['hrm_employee_uuid'] = $employeeUuid;
            }
            $this->users->update($user, $attributes);
        }

        $user = $this->assignDepartment($user->id, $departmentId);

        return $this->presentHrmEmployee($employee, $user);
    }

    /**
     * Toàn bộ nhân sự workspace đã có phòng ban — gom theo từng department
     * (kể cả inactive), dùng cho superadmin xem roster một lần gọi API.
     *
     * @param  \Illuminate\Support\Collection<int, \Modules\Identity\App\Models\Department>  $departments
     */
    public function departmentRosterGroups(Collection $departments): Collection
    {
        $usersByDepartment = $this->users->allWithAssignedDepartment()
            ->groupBy(fn (User $user) => (int) $user->department_id);

        return $departments->map(function ($department) use ($usersByDepartment) {
            $members = $usersByDepartment->get($department->id) ?? collect();

            return [
                'id' => $department->id,
                'name' => $department->name,
                'is_active' => (bool) $department->is_active,
                'member_count' => $members->count(),
                'members' => $members
                    ->map(fn (User $user) => $this->presentMember($user))
                    ->values()
                    ->all(),
            ];
        })->values();
    }

    /**
     * super_admin gán/đổi phòng ban cho 1 tài khoản — CHỈ gán department_id,
     * không đụng tới vai trò/nhóm (trưởng phòng tự gán vai trò sau khi
     * user đã có phòng ban, xem assignRole()).
     *
     * @throws MemberDepartmentNotAssignable
     */
    public function assignDepartment(int $userId, int $departmentId): User
    {
        $user = $this->users->findById($userId);
        if ($user === null) {
            throw new MemberDepartmentNotAssignable('Không tìm thấy tài khoản.');
        }

        $department = $this->departments->find($departmentId);
        if ($department === null) {
            throw new MemberDepartmentNotAssignable('Không tìm thấy phòng ban.');
        }

        if ((int) $user->department_id !== $departmentId) {
            $this->users->update($user, ['department_id' => $departmentId, 'team_id' => null]);
        }

        $user = $this->users->findById($userId);
        if ($user !== null) {
            $this->defaultMemberRole->ensureForUser($user);
        }

        return $user->fresh(['department', 'team', 'roles']) ?? $user;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, User>  $usersByHrmEmployeeUuid
     */
    private function presentDirectorForDepartment(
        Department $department,
        ?User $workspaceDirector,
        Collection $usersByHrmEmployeeUuid,
    ): ?array {
        if (HrmDepartmentSyncService::isConfigured() && filled($department->hrm_manager_name)) {
            $linked = filled($department->hrm_manager_employee_uuid)
                ? $usersByHrmEmployeeUuid->get($department->hrm_manager_employee_uuid)
                : null;

            return [
                'id' => $linked?->id,
                'name' => $department->hrm_manager_name,
                'email' => $department->hrm_manager_email ?? $linked?->email ?? '',
                'avatar_url' => $linked?->avatar_url,
            ];
        }

        return $this->presentDirector($workspaceDirector);
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
            'avatar_url' => $user->avatar_url,
        ];
    }

    /** @param  \Modules\Identity\App\Models\Company|null  $company */
    private function presentCompany($company): ?array
    {
        if ($company === null) {
            return null;
        }

        return [
            'id' => $company->id,
            'code' => $company->code,
            'name' => $company->name,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $employees
     * @return Collection<int, User>
     */
    private function usersForHrmEmployees(array $employees): Collection
    {
        $uuids = collect($employees)->pluck('uuid')->filter()->unique()->values();
        $emails = collect($employees)
            ->map(fn (array $employee) => $this->hrmEmail($employee))
            ->filter()
            ->unique()
            ->values();

        if ($uuids->isEmpty() && $emails->isEmpty()) {
            return collect();
        }

        return User::query()
            ->with(['department', 'team', 'roles'])
            ->where(function ($query) use ($uuids, $emails): void {
                if ($uuids->isNotEmpty()) {
                    $query->whereIn('hrm_employee_uuid', $uuids->all());
                }
                if ($emails->isNotEmpty()) {
                    $method = $uuids->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}(DB::raw('LOWER(email)'), $emails->all());
                }
            })
            ->get();
    }

    /**
     * @param  array<string, mixed>  $employee
     * @param  Collection<int, User>  $users
     */
    private function matchWorkspaceUser(array $employee, Collection $users): ?User
    {
        $matched = $users->first(
            fn (User $user) => $user->hrm_employee_uuid !== null && $user->hrm_employee_uuid === $employee['uuid']
        );
        if ($matched !== null) {
            return $matched;
        }

        $email = $this->hrmEmail($employee);
        if ($email === null) {
            return null;
        }

        return $users->first(
            fn (User $user) => strtolower((string) $user->email) === $email
                && ($user->hrm_employee_uuid === null || $user->hrm_employee_uuid === $employee['uuid'])
        );
    }

    /**
     * @param  array<string, mixed>  $employee
     * @return array<string, mixed>
     */
    private function presentHrmEmployee(array $employee, ?User $user): array
    {
        $user?->loadMissing(['department', 'team', 'roles']);

        return [
            'id' => $user?->id,
            'hrm_employee_uuid' => $employee['uuid'],
            'name' => filled($employee['full_name']) ? $employee['full_name'] : ($user?->name ?? ''),
            'email' => $employee['company_email'] ?? $user?->email,
            'avatar_url' => $user?->avatar_url,
            'status' => $employee['status'],
            'employee_code' => $employee['code'],
            'job_title_name' => $employee['job_title'],
            'job_position_level' => $employee['level_name'],
            'phone' => $employee['phone'],
            'company' => filled($employee['company_name']) ? [
                'code' => $employee['company_code'],
                'name' => $employee['company_name'],
            ] : null,
            'org_unit' => filled($employee['org_unit_name']) ? [
                'uuid' => $employee['org_unit_uuid'],
                'name' => $employee['org_unit_name'],
                'path' => $employee['org_unit_path'],
            ] : null,
            'department' => $user?->department ? [
                'id' => $user->department->id,
                'name' => $user->department->name,
            ] : null,
            'team' => $user?->team ? [
                'id' => $user->team->id,
                'name' => $user->team->name,
            ] : null,
            'roles' => $user
                ? $user->roles->map(fn ($role) => [
                    'code' => $role->code,
                    'name' => $role->name,
                ])->values()->all()
                : [],
            'manager_display_name' => $employee['manager_name'],
            'concurrent_positions' => $employee['concurrent_positions'] ?? [],
            'has_workspace_account' => $user !== null,
        ];
    }

    /**
     * @param  Collection<int, Department>  $departments
     * @param  Collection<int, array<string, mixed>>  $members
     */
    private function groupMembersByDepartment(Collection $departments, Collection $members): Collection
    {
        $byDepartment = $members
            ->filter(fn (array $member) => isset($member['department']['id']))
            ->groupBy(fn (array $member) => (int) $member['department']['id']);

        return $departments->map(function ($department) use ($byDepartment) {
            $rows = $byDepartment->get($department->id) ?? collect();

            return [
                'id' => $department->id,
                'name' => $department->name,
                'is_active' => (bool) $department->is_active,
                'member_count' => $rows->count(),
                'members' => $rows->values()->all(),
            ];
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $members
     * @param  Collection<int, array<string, mixed>>  $unassigned
     * @param  Collection<int, array<string, mixed>>  $groups
     * @param  Collection<int, Department>  $departments
     * @return array<string, mixed>
     */
    private function rosterPayload(
        string $source,
        Collection $members,
        Collection $unassigned,
        Collection $groups,
        Collection $departments,
    ): array {
        return [
            'source' => $source,
            'members' => $members->values()->all(),
            'unassigned' => $unassigned->values()->all(),
            'departments' => $groups->values()->all(),
            'department_options' => $departments->map(fn ($department) => [
                'id' => $department->id,
                'name' => $department->name,
            ])->values()->all(),
        ];
    }

    /** @param  array<string, mixed>  $employee */
    private function hrmEmail(array $employee): ?string
    {
        $email = $employee['company_email'] ?? $employee['personal_email'] ?? null;

        return is_string($email) && $email !== '' ? strtolower($email) : null;
    }
}
