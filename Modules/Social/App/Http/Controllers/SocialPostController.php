<?php

namespace Modules\Social\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\App\Services\ActivityLogService;
use Modules\Identity\App\Services\PermissionService;
use Modules\Social\App\Http\Requests\SetSocialReactionRequest;
use Modules\Social\App\Http\Requests\ShareSocialPostRequest;
use Modules\Social\App\Http\Requests\StoreSocialPostRequest;
use Modules\Social\App\Http\Requests\UpdateSocialPostRequest;
use Modules\Social\App\Models\SocialPostLike;
use Modules\Social\App\Repositories\Contracts\SocialGroupRepositoryInterface;
use Modules\Social\App\Repositories\Contracts\SocialPostRepositoryInterface;
use Modules\Social\App\Services\SocialGroupService;
use Modules\Social\App\Services\SocialPostService;

class SocialPostController extends Controller
{
    public function __construct(
        private readonly SocialPostService $service,
        private readonly SocialPostRepositoryInterface $posts,
        private readonly PermissionService $permissions,
        private readonly ActivityLogService $activityLogs,
        private readonly SocialGroupRepositoryInterface $groups,
        private readonly SocialGroupService $groupService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 10), 30);
        $page = max((int) $request->query('page', 1), 1);

        $scope = $request->query('scope', 'all');

        if ($this->groupAccessDenied($request, $this->requestedGroupId($request))) {
            return response()->json(['message' => 'Bạn không có quyền xem nhóm này.'], 403);
        }

        $wall = $this->resolveWall($request);

        if ($wall === false) {
            return response()->json(['message' => 'Bạn chưa thuộc phòng ban nào.'], 422);
        }

        return response()->json(
            $this->service->listFeed(
                $request->user(),
                $perPage,
                $page,
                is_string($scope) ? $scope : 'all',
                $wall['department_id'],
                $wall['wall_user_id'],
                $wall['group_id'],
            )
        );
    }

    public function show(Request $request, int $postId): JsonResponse
    {
        $post = $this->posts->find($postId, $request->user()->id);
        if (! $post) {
            return response()->json(['message' => 'Không tìm thấy bài viết.'], 404);
        }

        return response()->json(['post' => $this->service->present($post, $request->user())]);
    }

    /**
     * Suy ra tường từ query/body `post_scope` ('company'|'department'|'personal'|'group').
     * Trả về false nếu yêu cầu tường phòng ban nhưng user không thuộc phòng ban nào.
     *
     * @return array{department_id: int|null, wall_user_id: int|null, group_id: int|null}|false
     */
    private function resolveWall(Request $request): array|false
    {
        $postScope = $request->query('post_scope', $request->input('post_scope', 'company'));

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
            $wallUserId = $request->query('wall_user_id', $request->input('wall_user_id', $request->user()->id));

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
        if ($request->query('post_scope', $request->input('post_scope')) !== 'group') {
            return null;
        }

        $groupId = $request->query('group_id', $request->input('group_id'));

        return is_numeric($groupId) && (int) $groupId > 0 ? (int) $groupId : null;
    }

    /**
     * Chặn truy cập nhóm bảo mật khi viewer chưa là thành viên. Trả 403 (khác nghĩa
     * với `false` của resolveWall(), vốn là 422 "chưa thuộc phòng ban").
     */
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

    public function wall(Request $request, int $userId): JsonResponse
    {
        $profile = $this->service->wallProfile($userId, $request->user());
        if (! $profile) {
            return response()->json(['message' => 'Không tìm thấy người dùng.'], 404);
        }

        return response()->json($profile);
    }

    public function meStats(Request $request): JsonResponse
    {
        return response()->json($this->service->profileStats($request->user()));
    }

    public function revisions(Request $request, int $postId): JsonResponse
    {
        $post = $this->posts->find($postId);
        if (! $post) {
            return response()->json(['message' => 'Không tìm thấy bài viết.'], 404);
        }

        return response()->json($this->service->revisionHistory($post));
    }

    public function pinned(Request $request): JsonResponse
    {
        $scope = $request->query('scope', 'company');
        $wall = $this->resolveWall($request);

        if ($wall === false) {
            return response()->json(['message' => 'Bạn chưa thuộc phòng ban nào.'], 422);
        }

        $perPage = min(max((int) $request->query('per_page', 5), 1), 30);
        $page = max((int) $request->query('page', 1), 1);
        $search = is_string($request->query('q')) ? trim($request->query('q')) : '';

        return response()->json($this->service->pinned(
            $request->user(),
            is_string($scope) ? $scope : 'company',
            $perPage,
            $page,
            $wall['department_id'],
            $wall['wall_user_id'],
            $search !== '' ? $search : null,
        ));
    }

    public function store(StoreSocialPostRequest $request): JsonResponse
    {
        $post = $this->service->create(
            $request->user(),
            $request->validated(),
            $request->file('attachments', []),
        );

        return response()->json([
            'post' => $this->service->present($post, $request->user()),
        ], 201);
    }

    public function update(UpdateSocialPostRequest $request, int $postId): JsonResponse
    {
        $post = $this->posts->find($postId);
        if (! $post) {
            return response()->json(['message' => 'Không tìm thấy bài viết.'], 404);
        }

        $previousContent = (string) ($post->content ?? '');
        $post = $this->service->update($post, $request->user(), $request->validated());

        if ((string) ($post->content ?? '') !== $previousContent) {
            $this->activityLogs->record(
                'social_post.update',
                'Sửa bài viết "'.$this->activityContentSnippet($post->content).'"',
                $request->user(),
                'social_post',
                $post->id,
            );
        }

        return response()->json(['post' => $this->service->present($post, $request->user())]);
    }

    public function destroy(Request $request, int $postId): JsonResponse
    {
        $post = $this->posts->find($postId);
        if (! $post) {
            return response()->json(['message' => 'Không tìm thấy bài viết.'], 404);
        }

        $isOwner = (int) $post->user_id === (int) $request->user()->id;
        $canModerate = $post->user->department_id !== null
            && $this->permissions->allows($request->user(), 'social.moderate', 'department', $post->user->department_id);

        if (! $isOwner && ! $canModerate) {
            return response()->json(['message' => 'Bạn không có quyền xoá bài viết này.'], 403);
        }

        $this->service->delete($post);

        if (! $isOwner) {
            $this->activityLogs->record(
                'social_post.moderate_delete',
                'Xoá bài viết của "'.$post->user->name.'" trên bảng tin',
                $request->user(),
                'social_post',
                $post->id,
            );
        }

        return response()->json(['message' => 'Đã xoá bài viết.']);
    }

    public function share(ShareSocialPostRequest $request, int $postId): JsonResponse
    {
        $original = $this->posts->find($postId);
        if (! $original) {
            return response()->json(['message' => 'Không tìm thấy bài viết.'], 404);
        }

        $post = $this->service->share(
            $request->user(),
            $original,
            $request->validated()['caption'] ?? null,
            $request->validated(),
        );

        return response()->json([
            'post' => $this->service->present($post, $request->user()),
        ], 201);
    }

    public function setReaction(SetSocialReactionRequest $request, int $postId): JsonResponse
    {
        $post = $this->posts->find($postId);
        if (! $post) {
            return response()->json(['message' => 'Không tìm thấy bài viết.'], 404);
        }

        return response()->json(
            $this->service->setReaction($post, $request->user(), $request->validated()['type'])
        );
    }

    public function recordView(Request $request, int $postId): JsonResponse
    {
        $post = $this->posts->find($postId);
        if (! $post) {
            return response()->json(['message' => 'Không tìm thấy bài viết.'], 404);
        }

        return response()->json($this->service->recordView($post, $request->user()));
    }

    public function reactions(Request $request, int $postId): JsonResponse
    {
        $post = $this->posts->find($postId);
        if (! $post) {
            return response()->json(['message' => 'Không tìm thấy bài viết.'], 404);
        }

        $type = $this->validatedReactionType($request->query('type'));
        if ($type === false) {
            return response()->json(['message' => 'Loại cảm xúc không hợp lệ.'], 422);
        }

        return response()->json($this->service->reactionUsers($post, $type));
    }

    /** @return string|null|false  false = type không hợp lệ */
    private function validatedReactionType(mixed $type): string|null|false
    {
        if ($type === null || $type === '') {
            return null;
        }

        if (! is_string($type) || ! in_array($type, SocialPostLike::REACTION_TYPES, true)) {
            return false;
        }

        return $type;
    }

    public function pin(Request $request, int $postId): JsonResponse
    {
        $post = $this->posts->find($postId);
        if (! $post) {
            return response()->json(['message' => 'Không tìm thấy bài viết.'], 404);
        }

        if (! $this->permissions->allows($request->user(), 'social.pin', 'department', $request->user()->department_id)) {
            return response()->json(['message' => 'Bạn không có quyền ghim bài viết.'], 403);
        }

        $scope = $request->input('scope', 'company');
        $scope = is_string($scope) ? $scope : 'company';

        $post = $this->service->pin($post, $request->user(), $scope);

        $destination = $post->pin_scope === 'system' ? 'Thông báo quan trọng' : 'Thông báo công ty';

        $this->activityLogs->record(
            'social_post.pin',
            'Ghim bài viết "'.$this->activityContentSnippet($post->content).'" lên '.$destination,
            $request->user(),
            'social_post',
            $post->id,
        );

        return response()->json(['post' => $this->service->present($post, $request->user())]);
    }

    public function unpin(Request $request, int $postId): JsonResponse
    {
        $post = $this->posts->find($postId);
        if (! $post) {
            return response()->json(['message' => 'Không tìm thấy bài viết.'], 404);
        }

        if (! $this->permissions->allows($request->user(), 'social.pin', 'department', $request->user()->department_id)) {
            return response()->json(['message' => 'Bạn không có quyền bỏ ghim bài viết.'], 403);
        }

        $post = $this->service->unpin($post);

        return response()->json(['post' => $this->service->present($post, $request->user())]);
    }

    private function activityContentSnippet(?string $html, int $limit = 60): string
    {
        $plain = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8'));

        return mb_substr($plain, 0, $limit);
    }
}
