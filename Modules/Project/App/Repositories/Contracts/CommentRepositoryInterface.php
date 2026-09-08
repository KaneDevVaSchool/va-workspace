<?php

namespace Modules\Project\App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\Project\App\Models\Comment;

/**
 * Contract cho tầng Repository của Comment — nhận Model $commentable
 * (Task hoặc Project) thay vì gõ cứng 1 loại, vì bảng comments polymorphic.
 */
interface CommentRepositoryInterface
{
    /** Chỉ comment gốc (parent_comment_id null), kèm eager-load 2 cấp replies. */
    public function forCommentable(Model $commentable): Collection;

    public function find(int $id): ?Comment;

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Comment;

    public function delete(Comment $comment): void;

    public function countFor(Model $commentable): int;

    /**
     * Đặt/đổi/xoá reaction của user trên bình luận.
     * Trả ['action' => 'set'|'removed', 'reaction_type' => string|null].
     */
    public function setReaction(Comment $comment, int $userId, string $type): array;

    /** @return array<string, int> reaction_type => số lượng, kèm khoá 'total'. */
    public function reactionSummary(Comment $comment): array;

    /** @return Collection<int, \Modules\Project\App\Models\CommentReaction> */
    public function reactionUsers(Comment $comment, ?string $type = null): Collection;

    /** Ghim/bỏ ghim — chỉ có ý nghĩa với comment gốc, kiểm tra ở Service. */
    public function setPinned(Comment $comment, bool $pinned): Comment;

    /** Đánh dấu user đã đọc luồng thảo luận của $commentable đến thời điểm hiện tại (upsert). */
    public function markThreadRead(Model $commentable, int $userId): void;

    /** null nếu user chưa từng đọc luồng thảo luận này. */
    public function lastReadAt(Model $commentable, int $userId): ?\Illuminate\Support\Carbon;

    /** @return array<int, string> user_id (tác giả) => created_at (ISO 8601) của bình luận gốc mới nhất người đó viết. */
    public function latestCommentAtByAuthor(Model $commentable): array;
}
