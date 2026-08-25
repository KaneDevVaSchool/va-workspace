<?php

namespace Modules\Social\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Social\App\Http\Requests\SetSocialReactionRequest;
use Modules\Social\App\Http\Requests\StoreSocialCommentRequest;
use Modules\Social\App\Models\SocialPostLike;
use Modules\Social\App\Repositories\Contracts\SocialCommentRepositoryInterface;
use Modules\Social\App\Repositories\Contracts\SocialPostRepositoryInterface;
use Modules\Social\App\Services\SocialCommentService;

class SocialCommentController extends Controller
{
    public function __construct(
        private readonly SocialCommentService $service,
        private readonly SocialPostRepositoryInterface $posts,
        private readonly SocialCommentRepositoryInterface $comments,
    ) {}

    public function mentions(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        return response()->json([
            'users' => $this->service->searchMentions($request->user(), $query),
        ]);
    }

    public function index(Request $request, int $postId): JsonResponse
    {
        $post = $this->posts->find($postId);
        if (! $post) {
            return response()->json(['message' => 'Không tìm thấy bài viết.'], 404);
        }

        return response()->json(['comments' => $this->service->listForPost($post, $request->user())]);
    }

    public function store(StoreSocialCommentRequest $request, int $postId): JsonResponse
    {
        $post = $this->posts->find($postId);
        if (! $post) {
            return response()->json(['message' => 'Không tìm thấy bài viết.'], 404);
        }

        $validated = $request->validated();

        return response()->json(
            $this->service->create(
                $request->user(),
                $post,
                $validated['content'] ?? null,
                isset($validated['parent_comment_id']) ? (int) $validated['parent_comment_id'] : null,
                isset($validated['mentioned_user_id']) ? (int) $validated['mentioned_user_id'] : null,
                $request->file('attachments', []),
            ),
            201,
        );
    }

    public function destroy(Request $request, int $commentId): JsonResponse
    {
        $comment = $this->comments->find($commentId);
        if (! $comment) {
            return response()->json(['message' => 'Không tìm thấy bình luận.'], 404);
        }

        if (! $this->service->canDelete($comment, $request->user())) {
            return response()->json(['message' => 'Bạn không có quyền xoá bình luận này.'], 403);
        }

        $commentsCount = $this->service->delete($comment);

        return response()->json(['message' => 'Đã xoá bình luận.', 'comments_count' => $commentsCount]);
    }

    public function setReaction(SetSocialReactionRequest $request, int $commentId): JsonResponse
    {
        $comment = $this->comments->find($commentId);
        if (! $comment) {
            return response()->json(['message' => 'Không tìm thấy bình luận.'], 404);
        }

        return response()->json(
            $this->service->setReaction($comment, $request->user(), $request->validated()['type'])
        );
    }

    public function reactions(Request $request, int $commentId): JsonResponse
    {
        $comment = $this->comments->find($commentId);
        if (! $comment) {
            return response()->json(['message' => 'Không tìm thấy bình luận.'], 404);
        }

        $type = $request->query('type');
        if ($type === null || $type === '') {
            return response()->json($this->service->reactionUsers($comment));
        }

        if (! is_string($type) || ! in_array($type, SocialPostLike::REACTION_TYPES, true)) {
            return response()->json(['message' => 'Loại cảm xúc không hợp lệ.'], 422);
        }

        return response()->json($this->service->reactionUsers($comment, $type));
    }
}
