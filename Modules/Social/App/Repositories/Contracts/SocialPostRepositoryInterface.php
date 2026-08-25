<?php

namespace Modules\Social\App\Repositories\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Social\App\Models\SocialPost;

interface SocialPostRepositoryInterface
{
    public function paginate(int $perPage, int $page): LengthAwarePaginator;

    public function pinned(int $limit): Collection;

    public function find(int $id): ?SocialPost;

    public function create(array $data): SocialPost;

    public function update(SocialPost $post, array $data): SocialPost;

    public function delete(SocialPost $post): void;

    /**
     * Đặt/đổi/xoá reaction của user trên bài viết.
     * Trả ['action' => 'set'|'removed', 'reaction_type' => string|null].
     */
    public function setReaction(SocialPost $post, int $userId, string $type): array;

    public function myReaction(SocialPost $post, int $userId): ?string;

    /** @return array<string, int> reaction_type => số lượng, kèm khoá 'total'. */
    public function reactionSummary(SocialPost $post): array;
}
