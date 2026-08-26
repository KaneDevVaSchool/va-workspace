<?php

namespace Modules\Social\App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Social\App\Services\SocialGroupService;
use Modules\Social\App\Services\SocialHashtagService;
use Modules\Social\App\Repositories\Contracts\SocialGroupRepositoryInterface;

class SocialHashtagController
{
    public function __construct(
        private readonly SocialHashtagService $hashtags,
        private readonly SocialGroupRepositoryInterface $groups,
        private readonly SocialGroupService $groupService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($this->groupAccessDenied($request, $this->requestedGroupId($request))) {
            return response()->json(['message' => 'Bạn không có quyền xem nhóm này.'], 403);
        }

        $wall = $this->resolveWall($request);
        if ($wall === false) {
            return response()->json(['message' => 'Bạn chưa thuộc phòng ban nào.'], 422);
        }

        $limit = min(max((int) $request->query('limit', 12), 1), 30);
        $search = is_string($request->query('q')) ? trim($request->query('q')) : '';

        return response()->json([
            'hashtags' => $this->hashtags->recentForViewer(
                $request->user(),
                $wall['department_id'],
                $wall['wall_user_id'],
                $wall['group_id'],
                $limit,
                $search !== '' ? $search : null,
            ),
        ]);
    }

    /**
     * @return array{department_id: int|null, wall_user_id: int|null, group_id: int|null}|false
     */
    private function resolveWall(Request $request): array|false
    {
        $postScope = $request->query('post_scope', 'company');

        if ($postScope === 'group') {
            $groupId = $this->requestedGroupId($request);

            return $groupId === null
                ? false
                : ['department_id' => null, 'wall_user_id' => null, 'group_id' => $groupId];
        }

        if ($postScope === 'department') {
            $departmentId = $request->user()->department_id;

            return $departmentId === null
                ? false
                : ['department_id' => $departmentId, 'wall_user_id' => null, 'group_id' => null];
        }

        if ($postScope === 'personal') {
            $wallUserId = $request->query('wall_user_id', $request->user()->id);

            return [
                'department_id' => null,
                'wall_user_id' => (int) $wallUserId,
                'group_id' => null,
            ];
        }

        return ['department_id' => null, 'wall_user_id' => null, 'group_id' => null];
    }

    private function requestedGroupId(Request $request): ?int
    {
        if ($request->query('post_scope') !== 'group') {
            return null;
        }

        $groupId = $request->query('group_id');

        return is_numeric($groupId) && (int) $groupId > 0 ? (int) $groupId : null;
    }

    private function groupAccessDenied(Request $request, ?int $groupId): bool
    {
        if ($groupId === null) {
            return false;
        }

        $group = $this->groups->find($groupId);
        if ($group === null) {
            return true;
        }

        return ! $this->groupService->assertCanView($group, $request->user());
    }
}
