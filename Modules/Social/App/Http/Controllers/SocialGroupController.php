<?php

namespace Modules\Social\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Social\App\Http\Requests\StoreSocialGroupRequest;
use Modules\Social\App\Http\Requests\UpdateGroupMemberRoleRequest;
use Modules\Social\App\Http\Requests\UpdateSocialGroupRequest;
use Modules\Social\App\Repositories\Contracts\SocialGroupRepositoryInterface;
use Modules\Social\App\Services\SocialGroupService;

class SocialGroupController extends Controller
{
    public function __construct(
        private readonly SocialGroupService $service,
        private readonly SocialGroupRepositoryInterface $groups,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 12), 1), 30);
        $page = max((int) $request->query('page', 1), 1);
        $search = trim((string) $request->query('q', ''));
        $tab = $request->query('tab', 'mine');

        $result = $tab === 'discover'
            ? $this->service->listDiscoverable($request->user(), $perPage, $page, $search !== '' ? $search : null)
            : $this->service->listMine($request->user(), $perPage, $page, $search !== '' ? $search : null);

        return response()->json($result);
    }

    public function myJoinRequests(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 12), 1), 30);
        $page = max((int) $request->query('page', 1), 1);

        return response()->json($this->service->myJoinRequests($request->user(), $perPage, $page));
    }

    public function cancelJoinRequest(Request $request, int $requestId): JsonResponse
    {
        $joinRequest = $this->groups->findJoinRequest($requestId);
        if (! $joinRequest) {
            return response()->json(['message' => 'Không tìm thấy yêu cầu.'], 404);
        }

        $this->service->cancelMyJoinRequest($joinRequest, $request->user());

        return response()->json(['message' => 'Đã huỷ yêu cầu tham gia.']);
    }

    public function show(Request $request, int $groupId): JsonResponse
    {
        $group = $this->groups->find($groupId);
        if (! $group) {
            return response()->json(['message' => 'Không tìm thấy nhóm.'], 404);
        }

        $viewer = $request->user();
        $canView = $this->service->assertCanView($group, $viewer);

        return response()->json([
            'group' => $canView
                ? $this->service->present($group, $viewer)
                : $this->service->presentPreview($group, $viewer),
        ]);
    }

    public function store(StoreSocialGroupRequest $request): JsonResponse
    {
        $group = $this->service->create(
            $request->user(),
            $request->validated(),
            $request->file('cover'),
        );

        return response()->json(['group' => $this->service->present($group, $request->user())], 201);
    }

    public function update(UpdateSocialGroupRequest $request, int $groupId): JsonResponse
    {
        $group = $this->groups->find($groupId);
        if (! $group) {
            return response()->json(['message' => 'Không tìm thấy nhóm.'], 404);
        }

        $group = $this->service->update($group, $request->user(), $request->validated(), $request->file('cover'));

        return response()->json(['group' => $this->service->present($group, $request->user())]);
    }

    public function destroy(Request $request, int $groupId): JsonResponse
    {
        $group = $this->groups->find($groupId);
        if (! $group) {
            return response()->json(['message' => 'Không tìm thấy nhóm.'], 404);
        }

        $this->service->delete($group, $request->user());

        return response()->json(['message' => 'Đã xoá nhóm.']);
    }

    public function join(Request $request, int $groupId): JsonResponse
    {
        $group = $this->groups->find($groupId);
        if (! $group) {
            return response()->json(['message' => 'Không tìm thấy nhóm.'], 404);
        }

        $message = $request->input('message');
        $result = $this->service->join($group, $request->user(), $message);

        return response()->json([
            'status' => $result['status'],
            'group' => $this->service->present($this->groups->find($group->id), $request->user()),
        ]);
    }

    public function leave(Request $request, int $groupId): JsonResponse
    {
        $group = $this->groups->find($groupId);
        if (! $group) {
            return response()->json(['message' => 'Không tìm thấy nhóm.'], 404);
        }

        $this->service->leave($group, $request->user());

        return response()->json(['message' => 'Đã rời nhóm.']);
    }

    public function members(Request $request, int $groupId): JsonResponse
    {
        $group = $this->groups->find($groupId);
        if (! $group) {
            return response()->json(['message' => 'Không tìm thấy nhóm.'], 404);
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);
        $page = max((int) $request->query('page', 1), 1);
        $search = trim((string) $request->query('q', ''));

        return response()->json(
            $this->service->listMembers($group, $request->user(), $perPage, $page, $search !== '' ? $search : null)
        );
    }

    public function removeMember(Request $request, int $groupId, int $userId): JsonResponse
    {
        $group = $this->groups->find($groupId);
        if (! $group) {
            return response()->json(['message' => 'Không tìm thấy nhóm.'], 404);
        }

        $this->service->removeMember($group, $request->user(), $userId);

        return response()->json(['message' => 'Đã xoá thành viên khỏi nhóm.']);
    }

    public function updateMemberRole(UpdateGroupMemberRoleRequest $request, int $groupId, int $userId): JsonResponse
    {
        $group = $this->groups->find($groupId);
        if (! $group) {
            return response()->json(['message' => 'Không tìm thấy nhóm.'], 404);
        }

        $this->service->changeMemberRole($group, $request->user(), $userId, $request->validated()['role']);

        return response()->json(['message' => 'Đã cập nhật vai trò thành viên.']);
    }

    public function transferOwnership(Request $request, int $groupId, int $userId): JsonResponse
    {
        $group = $this->groups->find($groupId);
        if (! $group) {
            return response()->json(['message' => 'Không tìm thấy nhóm.'], 404);
        }

        $this->service->transferOwnership($group, $request->user(), $userId);

        return response()->json(['message' => 'Đã chuyển quyền chủ nhóm.']);
    }

    public function joinRequests(Request $request, int $groupId): JsonResponse
    {
        $group = $this->groups->find($groupId);
        if (! $group) {
            return response()->json(['message' => 'Không tìm thấy nhóm.'], 404);
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);
        $page = max((int) $request->query('page', 1), 1);

        return response()->json($this->service->joinRequests($group, $request->user(), $perPage, $page));
    }

    public function approveJoinRequest(Request $request, int $groupId, int $requestId): JsonResponse
    {
        $joinRequest = $this->groups->findJoinRequest($requestId);
        if (! $joinRequest || (int) $joinRequest->group_id !== $groupId) {
            return response()->json(['message' => 'Không tìm thấy yêu cầu.'], 404);
        }

        $this->service->approveJoinRequest($joinRequest, $request->user());

        return response()->json(['message' => 'Đã duyệt yêu cầu tham gia.']);
    }

    public function rejectJoinRequest(Request $request, int $groupId, int $requestId): JsonResponse
    {
        $joinRequest = $this->groups->findJoinRequest($requestId);
        if (! $joinRequest || (int) $joinRequest->group_id !== $groupId) {
            return response()->json(['message' => 'Không tìm thấy yêu cầu.'], 404);
        }

        $this->service->rejectJoinRequest($joinRequest, $request->user());

        return response()->json(['message' => 'Đã từ chối yêu cầu tham gia.']);
    }
}
