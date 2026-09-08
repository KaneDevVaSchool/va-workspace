<?php

namespace Modules\Project\App\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Project\App\Models\Comment;
use Modules\Project\App\Models\CommentThreadRead;
use Modules\Project\App\Repositories\Contracts\CommentRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent trực tiếp cho entity Comment.
 * Khuôn theo Modules\Social\App\Repositories\SocialCommentRepository, đổi
 * truy vấn "post_id cứng" sang quan hệ polymorphic $commentable->comments().
 */
class CommentRepository implements CommentRepositoryInterface
{
    public function forCommentable(Model $commentable): Collection
    {
        return $commentable->comments()
            ->with([
                'user',
                'mentionedUser',
                'attachments',
                'reactions',
                'replies.user',
                'replies.mentionedUser',
                'replies.attachments',
                'replies.reactions',
                'replies.replies.user',
                'replies.replies.mentionedUser',
                'replies.replies.attachments',
                'replies.replies.reactions',
            ])
            ->get();
    }

    public function find(int $id): ?Comment
    {
        return Comment::with(['user', 'mentionedUser', 'attachments', 'reactions'])->find($id);
    }

    public function create(array $data): Comment
    {
        $comment = Comment::create($data);

        return $this->find($comment->id);
    }

    public function delete(Comment $comment): void
    {
        $comment->loadMissing('replies');

        foreach ($comment->replies as $reply) {
            $this->delete($reply);
        }

        $comment->delete();
    }

    public function countFor(Model $commentable): int
    {
        return Comment::query()
            ->where('commentable_type', $commentable->getMorphClass())
            ->where('commentable_id', $commentable->getKey())
            ->count();
    }

    public function setReaction(Comment $comment, int $userId, string $type): array
    {
        $existing = $comment->reactions()->where('user_id', $userId)->first();

        if ($existing && $existing->reaction_type === $type) {
            $existing->delete();

            return ['action' => 'removed', 'reaction_type' => null];
        }

        if ($existing) {
            $existing->update(['reaction_type' => $type]);
        } else {
            $comment->reactions()->create(['user_id' => $userId, 'reaction_type' => $type]);
        }

        return ['action' => 'set', 'reaction_type' => $type];
    }

    public function reactionSummary(Comment $comment): array
    {
        $counts = $comment->reactions()
            ->selectRaw('reaction_type, count(*) as total')
            ->groupBy('reaction_type')
            ->pluck('total', 'reaction_type');

        $summary = [];
        foreach (Comment::REACTION_TYPES as $type) {
            $summary[$type] = (int) ($counts[$type] ?? 0);
        }
        $summary['total'] = array_sum($summary);

        return $summary;
    }

    public function reactionUsers(Comment $comment, ?string $type = null): Collection
    {
        $query = $comment->reactions()
            ->with(['user.department'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($type !== null) {
            $query->where('reaction_type', $type);
        }

        return $query->get();
    }

    public function setPinned(Comment $comment, bool $pinned): Comment
    {
        $comment->update([
            'is_pinned' => $pinned,
            'pinned_at' => $pinned ? now() : null,
        ]);

        return $this->find($comment->id);
    }

    public function markThreadRead(Model $commentable, int $userId): void
    {
        CommentThreadRead::updateOrCreate(
            [
                'commentable_type' => $commentable->getMorphClass(),
                'commentable_id' => $commentable->getKey(),
                'user_id' => $userId,
            ],
            ['last_read_at' => now()],
        );
    }

    public function lastReadAt(Model $commentable, int $userId): ?Carbon
    {
        $row = CommentThreadRead::query()
            ->where('commentable_type', $commentable->getMorphClass())
            ->where('commentable_id', $commentable->getKey())
            ->where('user_id', $userId)
            ->first();

        return $row?->last_read_at;
    }

    public function latestCommentAtByAuthor(Model $commentable): array
    {
        return Comment::query()
            ->where('commentable_type', $commentable->getMorphClass())
            ->where('commentable_id', $commentable->getKey())
            ->whereNull('parent_comment_id')
            ->selectRaw('user_id, MAX(created_at) as latest_at')
            ->groupBy('user_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->user_id => Carbon::parse($row->latest_at)->toIso8601String()])
            ->all();
    }
}
