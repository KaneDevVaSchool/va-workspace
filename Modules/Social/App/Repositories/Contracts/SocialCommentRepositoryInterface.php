<?php

namespace Modules\Social\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Social\App\Models\SocialPostComment;

interface SocialCommentRepositoryInterface
{
    public function forPost(int $postId): Collection;

    public function find(int $id): ?SocialPostComment;

    public function create(array $data): SocialPostComment;

    public function update(SocialPostComment $comment, array $data): SocialPostComment;

    public function delete(SocialPostComment $comment): void;

    public function countForPost(int $postId): int;

    /**
     * user_id => thời điểm bình luận gốc mới nhất (ISO 8601), dùng để xác định
     * người đã từng tham gia thảo luận trong bài viết (không tính reply lồng).
     *
     * @return array<int, string>
     */
    public function latestCommentAtByAuthor(int $postId): array;

    /**
     * Đặt/đổi/xoá reaction của user trên bình luận.
     * Trả ['action' => 'set'|'removed', 'reaction_type' => string|null].
     */
    public function setReaction(SocialPostComment $comment, int $userId, string $type): array;

    /** @return array<string, int> reaction_type => số lượng, kèm khoá 'total'. */
    public function reactionSummary(SocialPostComment $comment): array;

    /**
     * Danh sách người đã reaction, mới nhất trước.
     *
     * @return Collection<int, \Modules\Social\App\Models\SocialCommentLike>
     */
    public function reactionUsers(SocialPostComment $comment, ?string $type = null): Collection;
}
