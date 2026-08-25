<?php

namespace Modules\Social\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Social\App\Models\SocialPostComment;
use Modules\Social\App\Models\SocialPostLike;
use Modules\Social\App\Repositories\Contracts\SocialCommentRepositoryInterface;

class SocialCommentRepository implements SocialCommentRepositoryInterface
{
    /** Chỉ comment gốc (parent_comment_id null), kèm eager-load replies. */
    public function forPost(int $postId): Collection
    {
        return SocialPostComment::query()
            ->with([
                'user',
                'mentionedUser',
                'likes',
                'replies.user',
                'replies.mentionedUser',
                'replies.likes',
                'replies.replies.user',
                'replies.replies.mentionedUser',
                'replies.replies.likes',
            ])
            ->where('post_id', $postId)
            ->whereNull('parent_comment_id')
            ->orderBy('created_at')
            ->get();
    }

    public function find(int $id): ?SocialPostComment
    {
        return SocialPostComment::with(['user', 'mentionedUser', 'likes'])->find($id);
    }

    public function create(array $data): SocialPostComment
    {
        $comment = SocialPostComment::create($data);

        return $this->find($comment->id);
    }

    public function update(SocialPostComment $comment, array $data): SocialPostComment
    {
        $comment->update($data);

        return $this->find($comment->id);
    }

    public function delete(SocialPostComment $comment): void
    {
        $comment->loadMissing('replies');

        foreach ($comment->replies as $reply) {
            $this->delete($reply);
        }

        $comment->delete();
    }

    public function countForPost(int $postId): int
    {
        return SocialPostComment::query()->where('post_id', $postId)->count();
    }

    public function setReaction(SocialPostComment $comment, int $userId, string $type): array
    {
        $existing = $comment->likes()->where('user_id', $userId)->first();

        if ($existing && $existing->reaction_type === $type) {
            $existing->delete();

            return ['action' => 'removed', 'reaction_type' => null];
        }

        if ($existing) {
            $existing->update(['reaction_type' => $type]);
        } else {
            $comment->likes()->create(['user_id' => $userId, 'reaction_type' => $type]);
        }

        return ['action' => 'set', 'reaction_type' => $type];
    }

    public function reactionSummary(SocialPostComment $comment): array
    {
        $counts = $comment->likes()
            ->selectRaw('reaction_type, count(*) as total')
            ->groupBy('reaction_type')
            ->pluck('total', 'reaction_type');

        $summary = [];
        foreach (SocialPostLike::REACTION_TYPES as $type) {
            $summary[$type] = (int) ($counts[$type] ?? 0);
        }
        $summary['total'] = array_sum($summary);

        return $summary;
    }

    public function reactionUsers(SocialPostComment $comment, ?string $type = null): Collection
    {
        $query = $comment->likes()
            ->with(['user.department'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($type !== null) {
            $query->where('reaction_type', $type);
        }

        return $query->get();
    }
}
