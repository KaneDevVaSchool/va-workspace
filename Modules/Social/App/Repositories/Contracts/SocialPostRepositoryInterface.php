<?php

namespace Modules\Social\App\Repositories\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Social\App\Models\SocialPost;

interface SocialPostRepositoryInterface
{
    /**
     * @param  'all'|'mine'|'reacted'  $scope
     * @param  int|null  $departmentId  null trừ khi tường phòng ban
     * @param  int|null  $wallUserId  null trừ khi tường cá nhân
     * @param  int|null  $groupId  null trừ khi tường nhóm
     * @param  int|null  $viewerDepartmentId  phòng ban của viewer, để lọc bài giới hạn hiển thị theo phòng ban trên bảng tin chung
     */
    public function paginate(int $perPage, int $page, string $scope = 'all', ?int $userId = null, ?int $departmentId = null, ?int $wallUserId = null, ?int $groupId = null, ?int $viewerDepartmentId = null, ?string $hashtag = null): LengthAwarePaginator;

    /** Giới hạn bài viết theo tường / phòng ban / nhóm và quyền xem trên bảng tin chung. */
    public function constrainVisibleFeed($query, ?int $departmentId, ?int $wallUserId, ?int $groupId, ?int $viewerDepartmentId): void;

    /** @return array{posts_count: int, reactions_received: int, comments_count: int} */
    public function profileStats(int $userId): array;

    /**
     * @param  int|null  $departmentId  null trừ khi tường phòng ban
     * @param  int|null  $wallUserId  null trừ khi tường cá nhân
     */
    public function paginatePinned(
        int $perPage,
        int $page,
        string $scope = 'company',
        ?int $departmentId = null,
        ?int $wallUserId = null,
        ?string $search = null,
        ?int $viewerId = null,
        ?int $viewerDepartmentId = null,
    ): LengthAwarePaginator;

    public function find(int $id, ?int $viewerId = null): ?SocialPost;

    /**
     * Danh sách bài chờ duyệt (review_status = pending), cũ nhất trước (FIFO).
     */
    public function paginatePending(int $perPage, int $page): LengthAwarePaginator;

    public function create(array $data): SocialPost;

    /** Ghi các phòng ban được chọn (include/exclude) cho bài đăng bảng tin chung. */
    public function syncDepartmentVisibility(SocialPost $post, array $departmentIds): void;

    public function update(SocialPost $post, array $data): SocialPost;

    public function delete(SocialPost $post): void;

    public function addRevision(SocialPost $post, array $data): void;

    /** @return Collection<int, \Modules\Social\App\Models\SocialPostRevision> */
    public function revisions(SocialPost $post): Collection;

    /**
     * Đặt/đổi/xoá reaction của user trên bài viết.
     * Trả ['action' => 'set'|'removed', 'reaction_type' => string|null].
     */
    public function setReaction(SocialPost $post, int $userId, string $type): array;

    public function myReaction(SocialPost $post, int $userId): ?string;

    /** @return array<string, int> reaction_type => số lượng, kèm khoá 'total'. */
    public function reactionSummary(SocialPost $post): array;

    /**
     * Danh sách người đã reaction, mới nhất trước.
     *
     * @return Collection<int, \Modules\Social\App\Models\SocialPostLike>
     */
    public function reactionUsers(SocialPost $post, ?string $type = null): Collection;

    /** Ghi nhận lượt xem duy nhất. Trả true nếu vừa tạo bản ghi mới. */
    public function recordView(SocialPost $post, int $userId): bool;

    public function viewsCount(SocialPost $post): int;

    public function countPending(): int;
}
