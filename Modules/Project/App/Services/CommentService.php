<?php

namespace Modules\Project\App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;
use Modules\Project\App\Models\Comment;
use Modules\Project\App\Models\CommentReaction;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Repositories\Contracts\CommentRepositoryInterface;

/**
 * Business logic của "Thảo luận" (Comment) — rút gọn từ
 * Modules\Social\App\Services\SocialCommentService: bỏ hẳn permission
 * moderate (chỉ tác giả xoá được) và notification mention (không có trong
 * yêu cầu ban đầu); nhận Model $commentable polymorphic thay vì SocialPost.
 *
 * Inject thẳng ProjectService để tái dùng userCanManageDepartment() có sẵn
 * (quyền ghim bình luận dự án == quyền sửa dự án, mục "Ghim" trong plan) —
 * không tạo phụ thuộc vòng vì ProjectService không gọi ngược lại CommentService.
 */
class CommentService
{
    public function __construct(
        private readonly CommentRepositoryInterface $comments,
        private readonly CommentSanitizer $sanitizer,
        private readonly UserRepositoryInterface $users,
        private readonly ProjectService $projects,
    ) {}

    public function listFor(Model $commentable, User $viewer): array
    {
        $comments = $this->comments->forCommentable($commentable)
            ->map(fn (Comment $comment) => $this->present($comment, $viewer))
            ->values()
            ->all();

        return [
            'comments' => $comments,
            'unread_by_author' => $this->unreadByAuthor($commentable, $viewer),
        ];
    }

    /** @return array<int, bool> user_id (tác giả) => còn bình luận gốc chưa đọc của người đó hay không. */
    private function unreadByAuthor(Model $commentable, User $viewer): array
    {
        $lastReadAt = $this->comments->lastReadAt($commentable, $viewer->id);
        $latestByAuthor = $this->comments->latestCommentAtByAuthor($commentable);

        $result = [];
        foreach ($latestByAuthor as $authorId => $latestAtIso) {
            if ((int) $authorId === (int) $viewer->id) {
                continue;
            }
            $result[$authorId] = $lastReadAt === null || Carbon::parse($latestAtIso)->gt($lastReadAt);
        }

        return $result;
    }

    public function markThreadRead(Model $commentable, User $viewer): void
    {
        $this->comments->markThreadRead($commentable, $viewer->id);
    }

    /**
     * Chỉ comment GỐC (không phải trả lời) trong 1 Project ghim được, và
     * chỉ người có quyền sửa dự án (project.update_department) mới ghim —
     * dùng chung ProjectService::userCanManageDepartment() (mẫu canDelete()
     * bên dưới, Controller gọi trước khi thao tác, không throw exception).
     */
    public function canPin(Comment $comment, User $actor): bool
    {
        if ($comment->parent_comment_id !== null) {
            return false;
        }

        $commentable = $comment->commentable;

        return $commentable instanceof Project && $this->projects->userCanManageDepartment($actor, $commentable);
    }

    public function pin(Comment $comment, User $actor): array
    {
        return ['comment' => $this->present($this->comments->setPinned($comment, true), $actor)];
    }

    public function unpin(Comment $comment, User $actor): array
    {
        return ['comment' => $this->present($this->comments->setPinned($comment, false), $actor)];
    }

    public function searchMentions(User $viewer, string $query): array
    {
        return $this->users->searchActiveByName($query, 8, $viewer->id)
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar_url,
                'department' => $user->department?->name,
            ])
            ->values()
            ->all();
    }

    /** @param  UploadedFile[]  $files */
    public function create(
        User $author,
        Model $commentable,
        ?string $content,
        ?int $parentCommentId = null,
        ?int $mentionedUserId = null,
        array $files = [],
    ): array {
        if (! $commentable instanceof Task && ! $commentable instanceof Project) {
            throw new \InvalidArgumentException('Đối tượng không hỗ trợ bình luận.');
        }

        $resolvedParentId = null;
        $resolvedMentionId = $mentionedUserId;

        if ($parentCommentId !== null) {
            $parent = $this->comments->find($parentCommentId);

            if (! $parent
                || $parent->commentable_type !== $commentable->getMorphClass()
                || (int) $parent->commentable_id !== (int) $commentable->getKey()
            ) {
                throw ValidationException::withMessages([
                    'parent_comment_id' => ['Bình luận gốc không hợp lệ.'],
                ]);
            }

            $depth = $this->commentDepth($parent);

            if ($depth >= 2) {
                $resolvedParentId = $parent->parent_comment_id;
                $resolvedMentionId = $resolvedMentionId ?? $parent->user_id;
            } else {
                $resolvedParentId = $parent->id;
                if ($depth >= 1) {
                    $resolvedMentionId = $resolvedMentionId ?? $parent->user_id;
                }
            }
        }

        $clean = trim($this->sanitizer->sanitize($content ?? ''));
        $plain = trim(html_entity_decode(strip_tags($clean), ENT_QUOTES, 'UTF-8'));

        if ($plain === '' && $files === []) {
            throw ValidationException::withMessages([
                'content' => ['Bình luận phải có nội dung hoặc ít nhất 1 tệp đính kèm.'],
            ]);
        }

        $comment = $this->comments->create([
            'commentable_type' => $commentable->getMorphClass(),
            'commentable_id' => $commentable->getKey(),
            'parent_comment_id' => $resolvedParentId,
            'user_id' => $author->id,
            'mentioned_user_id' => $resolvedMentionId,
            'content' => $clean,
        ]);

        if ($files !== []) {
            $this->storeAttachments($comment, $files);
            $comment = $this->comments->find($comment->id);
        }

        return [
            'comment' => $this->present($comment, $author),
            'comments_count' => $this->comments->countFor($commentable),
        ];
    }

    public function setReaction(Comment $comment, User $user, string $type): array
    {
        $result = $this->comments->setReaction($comment, $user->id, $type);

        return [
            'reactions' => $this->comments->reactionSummary($comment),
            'my_reaction' => $result['reaction_type'],
        ];
    }

    public function reactionUsers(Comment $comment, ?string $type = null): array
    {
        return [
            'users' => $this->comments->reactionUsers($comment, $type)
                ->map(fn (CommentReaction $reaction) => $this->presentReactionUser($reaction->user, $reaction->reaction_type))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /** @return array{type: string, user: array{id: int, name: string, avatar_url: mixed, department: string|null}}|null */
    private function presentReactionUser(?User $user, string $type): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'type' => $type,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar_url,
                'department' => $user->department?->name,
            ],
        ];
    }

    /** Chỉ tác giả bình luận mới xoá được — không permission tĩnh. */
    public function canDelete(Comment $comment, User $actor): bool
    {
        return (int) $comment->user_id === (int) $actor->id;
    }

    public function delete(Comment $comment): int
    {
        $commentableType = $comment->commentable_type;
        $commentableId = $comment->commentable_id;
        $comment->loadMissing(['replies.replies', 'attachments', 'replies.attachments', 'replies.replies.attachments']);

        $this->deleteTreeAttachments($comment);
        $this->comments->delete($comment);

        return Comment::query()
            ->where('commentable_type', $commentableType)
            ->where('commentable_id', $commentableId)
            ->count();
    }

    /** 0 = gốc, 1 = trả lời, 2 = trả lời lồng. */
    private function commentDepth(Comment $comment): int
    {
        if ($comment->parent_comment_id === null) {
            return 0;
        }

        $parent = $this->comments->find($comment->parent_comment_id);

        if (! $parent || $parent->parent_comment_id === null) {
            return 1;
        }

        return 2;
    }

    private function deleteTreeAttachments(Comment $comment): void
    {
        foreach ($comment->attachments as $attachment) {
            if ($attachment->file_path) {
                Storage::disk('public')->delete($attachment->file_path);
            }
        }

        if (! $comment->relationLoaded('replies')) {
            return;
        }

        foreach ($comment->replies as $reply) {
            $this->deleteTreeAttachments($reply);
        }
    }

    public function present(Comment $comment, User $viewer): array
    {
        $reactions = $this->reactionPayload($comment, $viewer);

        return [
            'id' => $comment->id,
            'parent_comment_id' => $comment->parent_comment_id,
            'content' => $comment->content,
            'attachments' => $comment->attachments->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'name' => $a->file_name,
                'size' => $a->file_size,
                'url' => Storage::disk('public')->url($a->file_path),
            ])->all(),
            'author' => [
                'id' => $comment->user->id,
                'name' => $comment->user->name,
                'avatar_url' => $comment->user->avatar_url,
            ],
            'mentioned_user' => $comment->mentionedUser ? [
                'id' => $comment->mentionedUser->id,
                'name' => $comment->mentionedUser->name,
            ] : null,
            'reactions' => $reactions['reactions'],
            'my_reaction' => $reactions['my_reaction'],
            'replies' => $comment->relationLoaded('replies')
                ? $comment->replies->map(fn (Comment $reply) => $this->present($reply, $viewer))->values()->all()
                : [],
            'is_pinned' => (bool) $comment->is_pinned,
            'pinned_at' => $comment->pinned_at?->toIso8601String(),
            'created_at' => $comment->created_at?->toIso8601String(),
        ];
    }

    /** @return array{reactions: array<string, int>, my_reaction: string|null} */
    private function reactionPayload(Comment $comment, User $viewer): array
    {
        $reactionRecords = $comment->relationLoaded('reactions')
            ? $comment->reactions
            : $comment->reactions()->get();

        $summary = [];
        foreach (Comment::REACTION_TYPES as $type) {
            $summary[$type] = 0;
        }

        $myReaction = null;
        foreach ($reactionRecords as $reaction) {
            if (! isset($summary[$reaction->reaction_type])) {
                continue;
            }
            $summary[$reaction->reaction_type]++;
            if ((int) $reaction->user_id === (int) $viewer->id) {
                $myReaction = $reaction->reaction_type;
            }
        }
        $summary['total'] = 0;
        foreach (Comment::REACTION_TYPES as $type) {
            $summary['total'] += $summary[$type];
        }

        return [
            'reactions' => $summary,
            'my_reaction' => $myReaction,
        ];
    }

    /** @param  UploadedFile[]  $files */
    private function storeAttachments(Comment $comment, array $files): void
    {
        $imageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        foreach ($files as $file) {
            $path = $file->store('comments/'.$comment->id, 'public');

            $comment->attachments()->create([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'type' => in_array($file->getMimeType(), $imageMimes, true) ? 'image' : 'file',
            ]);
        }
    }
}
