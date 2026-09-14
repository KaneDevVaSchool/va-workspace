<?php

namespace Modules\Project\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Project\App\Http\Requests\SetCommentReactionRequest;
use Modules\Project\App\Http\Requests\StoreCommentRequest;
use Modules\Project\App\Models\Comment;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Repositories\Contracts\CommentRepositoryInterface;
use Modules\Project\App\Services\CommentService;
use Modules\Identity\App\Services\ActivityLogService;

/**
 * Controller mỏng: chỉ nhận request, gọi Service, trả response — comment
 * dùng chung cho cả Task và Project (polymorphic), nhưng tách 2 cặp
 * method index/store riêng theo loại cha để dùng route model binding tự
 * nhiên (Task $task / Project $project) + permission khác nhau ở route.
 */
class CommentController extends Controller
{
    public function __construct(
        private readonly CommentService $service,
        private readonly CommentRepositoryInterface $comments,
        private readonly ActivityLogService $activityLogs,
    ) {}

    public function mentions(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        return response()->json([
            'users' => $this->service->searchMentions($request->user(), $query),
        ]);
    }

    public function taskIndex(Request $request, Task $task): JsonResponse
    {
        return response()->json($this->service->listFor($task, $request->user()));
    }

    public function taskStore(StoreCommentRequest $request, Task $task): JsonResponse
    {
        $validated = $request->validated();

        return response()->json(
            $this->service->create(
                $request->user(),
                $task,
                $validated['content'] ?? null,
                isset($validated['parent_comment_id']) ? (int) $validated['parent_comment_id'] : null,
                isset($validated['mentioned_user_id']) ? (int) $validated['mentioned_user_id'] : null,
                $request->file('attachments', []),
            ),
            201,
        );
    }

    public function projectIndex(Request $request, Project $project): JsonResponse
    {
        return response()->json($this->service->listFor($project, $request->user()));
    }

    public function projectStore(StoreCommentRequest $request, Project $project): JsonResponse
    {
        $validated = $request->validated();

        return response()->json(
            $this->service->create(
                $request->user(),
                $project,
                $validated['content'] ?? null,
                isset($validated['parent_comment_id']) ? (int) $validated['parent_comment_id'] : null,
                isset($validated['mentioned_user_id']) ? (int) $validated['mentioned_user_id'] : null,
                $request->file('attachments', []),
            ),
            201,
        );
    }

    public function destroy(Request $request, int $comment): JsonResponse
    {
        $model = $this->comments->find($comment);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy bình luận.'], 404);
        }

        if (! $this->service->canDelete($model, $request->user())) {
            return response()->json(['message' => 'Bạn không có quyền xoá bình luận này.'], 403);
        }

        $commentsCount = $this->service->delete($model);

        $this->activityLogs->record(
            'comment.delete',
            'Xoá bình luận',
            $request->user(),
            'comment',
            $model->id,
        );

        return response()->json(['message' => 'Đã xoá bình luận.', 'comments_count' => $commentsCount]);
    }

    public function setReaction(SetCommentReactionRequest $request, int $comment): JsonResponse
    {
        $model = $this->comments->find($comment);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy bình luận.'], 404);
        }

        return response()->json(
            $this->service->setReaction($model, $request->user(), $request->validated()['type'])
        );
    }

    public function reactions(Request $request, int $comment): JsonResponse
    {
        $model = $this->comments->find($comment);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy bình luận.'], 404);
        }

        $type = $request->query('type');
        if ($type === null || $type === '') {
            return response()->json($this->service->reactionUsers($model));
        }

        if (! is_string($type) || ! in_array($type, Comment::REACTION_TYPES, true)) {
            return response()->json(['message' => 'Loại cảm xúc không hợp lệ.'], 422);
        }

        return response()->json($this->service->reactionUsers($model, $type));
    }

    public function pin(Request $request, int $comment): JsonResponse
    {
        $model = $this->comments->find($comment);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy bình luận.'], 404);
        }

        if (! $this->service->canPin($model, $request->user())) {
            return response()->json(['message' => 'Bạn không có quyền ghim bình luận này.'], 403);
        }

        $result = $this->service->pin($model, $request->user());

        $this->activityLogs->record(
            'comment.pin',
            'Ghim bình luận',
            $request->user(),
            'comment',
            $model->id,
        );

        return response()->json($result);
    }

    public function unpin(Request $request, int $comment): JsonResponse
    {
        $model = $this->comments->find($comment);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy bình luận.'], 404);
        }

        if (! $this->service->canPin($model, $request->user())) {
            return response()->json(['message' => 'Bạn không có quyền ghim bình luận này.'], 403);
        }

        $result = $this->service->unpin($model, $request->user());

        $this->activityLogs->record(
            'comment.unpin',
            'Bỏ ghim bình luận',
            $request->user(),
            'comment',
            $model->id,
        );

        return response()->json($result);
    }

    public function markThreadRead(Request $request, Project $project): JsonResponse
    {
        $this->service->markThreadRead($project, $request->user());

        return response()->json(['message' => 'Đã đánh dấu đã đọc.']);
    }
}
