<?php

namespace Modules\WorkspaceConfig\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\App\Exceptions\TeamLeadNotInDepartment;
use Modules\Identity\App\Services\ActivityLogService;
use Modules\Identity\App\Services\PermissionService;
use Modules\WorkspaceConfig\App\Exceptions\MemberTeamNotAssignable;
use Modules\WorkspaceConfig\App\Exceptions\RoleNotAssignable;
use Modules\WorkspaceConfig\App\Http\Requests\AssignWorkspaceConfigMemberTeamRequest;
use Modules\WorkspaceConfig\App\Http\Requests\AssignWorkspaceConfigRoleRequest;
use Modules\WorkspaceConfig\App\Http\Requests\StoreWorkspaceConfigTeamRequest;
use Modules\WorkspaceConfig\App\Services\WorkspaceConfigMemberService;

/**
 * manager JSON:
 *   GET  /api/workspace-config/members — thành viên + nhóm của CHÍNH phòng
 *        ban user đang đăng nhập.
 *   POST /api/workspace-config/members/teams — tạo nhóm trong phòng ban đó.
 *   PUT  /api/workspace-config/members/teams/{team} — sửa tên / trưởng nhóm.
 *   POST /api/workspace-config/members/roles — gán vai trò cho thành viên
 *        cùng phòng ban.
 *
 * department_id luôn lấy từ $request->user()->department_id, KHÔNG nhận từ
 * query/body — trưởng phòng chỉ được xem/tạo đúng phòng ban của mình (khác
 * TeamController, nơi 1 user có thể quản nhiều phòng ban qua department_id).
 *
 * Path JSON khác path trang Vue /manager/workspace-config/members để F5
 * không bị Laravel trả JSON.
 */
class WorkspaceConfigMemberController extends Controller
{
    public function __construct(
        private readonly WorkspaceConfigMemberService $service,
        private readonly PermissionService $permissions,
        private readonly ActivityLogService $activityLogs,
    ) {}

    private function departmentIdOrFail(Request $request): int|JsonResponse
    {
        $departmentId = $request->user()?->department_id;

        return $departmentId
            ? (int) $departmentId
            : response()->json(['message' => 'Tài khoản chưa gắn với phòng ban nào.'], 422);
    }

    public function index(Request $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        $this->service->syncDepartmentTeamLeadMemberships($departmentId);

        $user = $request->user();
        $user->loadMissing('department');
        $department = $user->department;

        return response()->json([
            'department' => $department ? [
                'id' => $department->id,
                'code' => $department->code,
                'name' => $department->name,
            ] : null,
            'members' => $this->service->forDepartment($departmentId),
            'teams' => $this->service->teamsForDepartment($departmentId),
            'assignable_roles' => $this->service->assignableRoles(),
        ]);
    }

    public function storeTeam(StoreWorkspaceConfigTeamRequest $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'team.manage', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền quản lý nhóm của phòng ban này.'], 403);
        }

        try {
            $team = $this->service->createTeam($departmentId, $request->validated());
        } catch (TeamLeadNotInDepartment $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->activityLogs->record(
            'team.create',
            'Tạo nhóm '.$team->name,
            $request->user(),
            'team',
            $team->id,
        );

        return response()->json(['team' => $this->service->presentTeam($team)], 201);
    }

    public function updateTeam(StoreWorkspaceConfigTeamRequest $request, int $team): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'team.manage', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền quản lý nhóm của phòng ban này.'], 403);
        }

        try {
            $updated = $this->service->updateTeam($departmentId, $team, $request->validated());
        } catch (TeamLeadNotInDepartment $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($updated === null) {
            return response()->json(['message' => 'Không tìm thấy nhóm trong phòng ban này.'], 404);
        }

        $this->activityLogs->record(
            'team.update',
            'Cập nhật nhóm '.$updated->name,
            $request->user(),
            'team',
            $updated->id,
        );

        return response()->json(['team' => $this->service->presentTeam($updated)]);
    }

    public function assignRole(AssignWorkspaceConfigRoleRequest $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'workspace_config.assign_role_department', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền gán vai trò trong phòng ban này.'], 403);
        }

        $data = $request->validated();

        try {
            $member = $this->service->assignRole(
                $departmentId,
                (int) $request->user()->id,
                (int) $data['user_id'],
                (string) $data['role_code'],
            );
        } catch (RoleNotAssignable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $presented = $this->service->presentMember($member);
        $roleName = collect($presented['roles'])->pluck('name')->implode(', ') ?: $data['role_code'];

        $this->activityLogs->record(
            'role.assign',
            'Gán vai trò '.$roleName.' cho '.$member->name,
            $request->user(),
            'user',
            $member->id,
            ['role_code' => $data['role_code']],
        );

        return response()->json(['member' => $presented]);
    }

    public function assignMemberTeam(AssignWorkspaceConfigMemberTeamRequest $request, int $user): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->permissions->allows($request->user(), 'team.manage', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền quản lý nhóm của phòng ban này.'], 403);
        }

        $teamId = $request->validated()['team_id'] ?? null;
        $teamId = $teamId === null ? null : (int) $teamId;

        try {
            $member = $this->service->assignMemberTeam(
                $departmentId,
                (int) $request->user()->id,
                $user,
                $teamId,
            );
        } catch (MemberTeamNotAssignable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $presented = $this->service->presentMember($member);
        $teamName = $presented['team']['name'] ?? null;

        $this->activityLogs->record(
            'member.team.assign',
            $teamName
                ? 'Gán nhóm '.$teamName.' cho '.$member->name
                : 'Bỏ gán nhóm cho '.$member->name,
            $request->user(),
            'user',
            $member->id,
            ['team_id' => $teamId],
        );

        return response()->json(['member' => $presented]);
    }
}
