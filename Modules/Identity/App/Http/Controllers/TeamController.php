<?php

namespace Modules\Identity\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\App\Exceptions\TeamLeadNotInDepartment;
use Modules\Identity\App\Models\Team;
use Modules\Identity\App\Http\Requests\StoreTeamRequest;
use Modules\Identity\App\Http\Requests\UpdateTeamRequest;
use Modules\Identity\App\Repositories\Contracts\TeamRepositoryInterface;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;
use Modules\Identity\App\Services\PermissionService;
use Modules\Identity\App\Services\TeamService;

/**
 * manager/teams — CRUD team đầy đủ theo phòng ban (mẫu department_director
 * quản lý team của phòng mình).
 *
 * Route chỉ bọc `permission:team.manage` (global) ở routes/manager.php vì
 * department_id đến từ query/body, không phải route param — middleware
 * `permission:...,department,{param}` chỉ đọc được scope_id từ route
 * parameter nên không áp dụng được ở đây. Enforce đúng phạm vi department
 * ngay trong controller bằng PermissionService::allows() với scope thật.
 */
class TeamController extends Controller
{
    public function __construct(
        private readonly TeamRepositoryInterface $teams,
        private readonly UserRepositoryInterface $users,
        private readonly TeamService $service,
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

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($deny = $this->denyUnlessCanManage($request, (int) $data['department_id'])) {
            return $deny;
        }

        try {
            $team = $this->service->create($data);
        } catch (TeamLeadNotInDepartment $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['team' => $this->present($team)], 201);
    }

    public function update(UpdateTeamRequest $request, int $team): JsonResponse
    {
        $model = $this->teams->find($team);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy nhóm.'], 404);
        }

        if ($deny = $this->denyUnlessCanManage($request, $model->department_id)) {
            return $deny;
        }

        try {
            $model = $this->service->update($model, $request->validated());
        } catch (TeamLeadNotInDepartment $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['team' => $this->present($model)]);
    }

    public function destroy(Request $request, int $team): JsonResponse
    {
        $model = $this->teams->find($team);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy nhóm.'], 404);
        }

        if ($deny = $this->denyUnlessCanManage($request, $model->department_id)) {
            return $deny;
        }

        $this->service->delete($model);

        return response()->json(['message' => 'Đã xoá nhóm.']);
    }
}
