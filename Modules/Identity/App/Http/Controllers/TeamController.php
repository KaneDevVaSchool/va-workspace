<?php

namespace Modules\Identity\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\App\Models\Team;
use Modules\Identity\App\Repositories\Contracts\TeamRepositoryInterface;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;
use Modules\Identity\App\Services\PermissionService;

/**
 * GET manager/teams — danh sách nhóm theo department_id (query).
 * Dùng cho PermissionScopeFilter trên ma trận phân quyền.
 * Tạo/sửa/xoá nhóm: API WorkspaceConfig members.
 */
class TeamController extends Controller
{
    public function __construct(
        private readonly TeamRepositoryInterface $teams,
        private readonly UserRepositoryInterface $users,
        private readonly PermissionService $permissions,
    ) {}

    /** Từ chối nếu user không có quyền team.manage trong đúng department này. */
    private function denyUnlessCanManage(Request $request, int $departmentId): ?JsonResponse
    {
        if (! $this->permissions->allows($request->user(), 'team.manage', 'department', $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền quản lý nhóm của phòng ban này.'], 403);
        }

        return null;
    }

    private function present(Team $team): array
    {
        return [
            'id' => $team->id,
            'department_id' => $team->department_id,
            'name' => $team->name,
            'team_lead_id' => $team->team_lead_id,
            'team_lead' => $team->teamLead ? [
                'id' => $team->teamLead->id,
                'name' => $team->teamLead->name,
                'email' => $team->teamLead->email,
            ] : null,
            'hrm_team_uuid' => $team->hrm_team_uuid,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $departmentId = (int) $request->query('department_id');

        if ($deny = $this->denyUnlessCanManage($request, $departmentId)) {
            return $deny;
        }

        $teams = $this->teams->allByDepartment($departmentId);
        $members = $this->users->allActiveByDepartment($departmentId);

        return response()->json([
            'teams' => $teams->map(fn (Team $team) => $this->present($team))->values(),
            'department_members' => $members->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])->values(),
        ]);
    }
}
