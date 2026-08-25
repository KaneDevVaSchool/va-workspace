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
use Modules\Social\App\Repositories\Contracts\SocialPostRepositoryInterface;
use Modules\Social\App\Services\SocialPostService;

class SocialPostController extends Controller
{
    public function __construct(
        private readonly SocialPostService $service,
        private readonly SocialPostRepositoryInterface $posts,
        private readonly PermissionService $permissions,
        private readonly ActivityLogService $activityLogs,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 10), 30);
        $page = max((int) $request->query('page', 1), 1);

        return response()->json(
            $this->service->listFeed($request->user(), $perPage, $page)
        );
    }

    public function pinned(Request $request): JsonResponse
    {
        return response()->json([
            'posts' => $this->service->pinned($request->user()),
        ]);
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

        $post = $this->service->update($post, $request->user(), $request->validated());

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

        $post = $this->service->share($request->user(), $original, $request->validated()['caption'] ?? null);

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

    public function pin(Request $request, int $postId): JsonResponse
    {
        $post = $this->posts->find($postId);
        if (! $post) {
            return response()->json(['message' => 'Không tìm thấy bài viết.'], 404);
        }

        if (! $this->permissions->allows($request->user(), 'social.pin', 'department', $request->user()->department_id)) {
            return response()->json(['message' => 'Bạn không có quyền ghim bài viết.'], 403);
        }

        $post = $this->service->pin($post, $request->user());

        $this->activityLogs->record(
            'social_post.pin',
            'Ghim bài viết "'.mb_substr((string) $post->content, 0, 60).'" lên Thông báo công ty',
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
}
