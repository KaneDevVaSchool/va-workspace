<?php

namespace Modules\Social\App\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Social\App\Models\SocialPost;
use Modules\Social\App\Models\SocialPostComment;
use Modules\Social\App\Models\SocialPostLike;
use Modules\Social\App\Repositories\Contracts\SocialPostRepositoryInterface;

class SocialPostRepository implements SocialPostRepositoryInterface
{
    private function baseQuery(?int $viewerId = null)
    {
        $query = SocialPost::query()
            ->with([
                'user.department',
                'pinnedBy',
                'reviewedBy',
                'sharedFrom.user',
                'wallUser.department',
                'group',
                'departmentVisibilities.department',
                'hashtags',
                'poll.options' => fn ($query) => $query
                    ->withCount('votes')
                    ->orderBy('position')
                    ->orderBy('id'),
            ])
            ->withCount(['comments', 'views']);

        if ($viewerId !== null) {
            $query->withExists([
                'views as viewed' => fn ($views) => $views->where('user_id', $viewerId),
            ]);
        }

        return $query;
    }

    public function paginate(int $perPage, int $page, string $scope = 'all', ?int $userId = null, ?int $departmentId = null, ?int $wallUserId = null, ?int $groupId = null, ?int $viewerDepartmentId = null, ?string $hashtag = null): LengthAwarePaginator
    {
        $query = $this->baseQuery($userId);
        $this->constrainVisibleFeed($query, $departmentId, $wallUserId, $groupId, $viewerDepartmentId);

        // Bài đang chờ duyệt/bị từ chối chỉ hiện cho chính tác giả ở scope 'mine'.
        if ($userId !== null && $scope === 'mine') {
            $query->where('user_id', $userId);
        } else {
            $query->where('review_status', SocialPost::REVIEW_APPROVED);
        }

        if ($userId !== null && $scope === 'reacted') {
            $query->whereHas('likes', fn ($likes) => $likes->where('user_id', $userId));
        }

        if ($hashtag !== null && $hashtag !== '') {
            $query->whereHas('hashtags', fn ($tags) => $tags->where('name', $hashtag));
        }

        return $query
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function constrainVisibleFeed($query, ?int $departmentId, ?int $wallUserId, ?int $groupId, ?int $viewerDepartmentId): void
    {
        $this->applyWall($query, $departmentId, $wallUserId, $groupId);
        $this->applyDepartmentVisibility($query, $departmentId, $wallUserId, $groupId, $viewerDepartmentId);
    }

    public function profileStats(int $userId): array
    {
        $postIds = SocialPost::query()->where('user_id', $userId)->select('id');

        return [
            'posts_count' => SocialPost::query()->where('user_id', $userId)->count(),
            'reactions_received' => SocialPostLike::query()->whereIn('post_id', $postIds)->count(),
            'comments_count' => SocialPostComment::query()->where('user_id', $userId)->count(),
        ];
    }

    public function paginatePinned(
        int $perPage,
        int $page,
        string $scope = 'company',
        ?int $departmentId = null,
        ?int $wallUserId = null,
        ?string $search = null,
        ?int $viewerId = null,
        ?int $viewerDepartmentId = null,
    ): LengthAwarePaginator {
        $query = $this->baseQuery($viewerId)
            ->where('is_pinned', true)
            ->where('pin_scope', $scope)
            ->where('review_status', SocialPost::REVIEW_APPROVED);

        $this->applyWall($query, $departmentId, $wallUserId);
        $this->applyDepartmentVisibility($query, $departmentId, $wallUserId, null, $viewerDepartmentId);
        $this->applyPinnedSearch($query, $search);

        return $query
            ->orderByDesc('pinned_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    private function applyPinnedSearch($query, ?string $search): void
    {
        $needle = trim((string) $search);
        if ($needle === '') {
            return;
        }

        $like = '%'.addcslashes($needle, '%_\\').'%';

        $query->where(function ($inner) use ($like) {
            $inner->where('content', 'like', $like)
                ->orWhereHas('user', fn ($users) => $users->where('name', 'like', $like))
                ->orWhereHas('poll', fn ($polls) => $polls
                    ->where('title', 'like', $like)
                    ->orWhere('content', 'like', $like));
        });
    }

    private function applyWall($query, ?int $departmentId, ?int $wallUserId, ?int $groupId = null): void
    {
        if ($groupId !== null) {
            $query->where('group_id', $groupId)->whereNull('department_id')->whereNull('wall_user_id');

            return;
        }

        if ($wallUserId !== null) {
            $query->where('wall_user_id', $wallUserId)->whereNull('department_id')->whereNull('group_id');

            return;
        }

        if ($departmentId !== null) {
            $query->where('department_id', $departmentId)->whereNull('wall_user_id')->whereNull('group_id');

            return;
        }

        $query->whereNull('department_id')->whereNull('wall_user_id')->whereNull('group_id');
    }

    /**
     * Chỉ áp dụng cho bảng tin chung (department/wall/group đều null): ẩn bài mà
     * $viewerDepartmentId không được thấy theo department_visibility_mode.
     * Viewer chưa thuộc phòng ban nào không thể nằm trong danh sách 'include' của
     * bất kỳ bài nào (nên bỏ nhánh này), nhưng vẫn không thuộc danh sách bị trừ
     * của bất kỳ bài 'exclude' nào — nên vẫn thấy được các bài đó.
     */
    private function applyDepartmentVisibility($query, ?int $departmentId, ?int $wallUserId, ?int $groupId, ?int $viewerDepartmentId): void
    {
        if ($departmentId !== null || $wallUserId !== null || $groupId !== null) {
            return;
        }

        $query->where(function ($outer) use ($viewerDepartmentId) {
            $outer->where('department_visibility_mode', SocialPost::DEPARTMENT_VISIBILITY_ALL)
                ->orWhereNull('department_visibility_mode')
                ->orWhere(function ($exclude) use ($viewerDepartmentId) {
                    $exclude->where('department_visibility_mode', SocialPost::DEPARTMENT_VISIBILITY_EXCLUDE);

                    if ($viewerDepartmentId === null) {
                        return;
                    }

                    $exclude->whereDoesntHave('departmentVisibilities', fn ($v) => $v->where('department_id', $viewerDepartmentId));
                });

            if ($viewerDepartmentId === null) {
                return;
            }

            $outer->orWhere(function ($include) use ($viewerDepartmentId) {
                $include->where('department_visibility_mode', SocialPost::DEPARTMENT_VISIBILITY_INCLUDE)
                    ->whereHas('departmentVisibilities', fn ($v) => $v->where('department_id', $viewerDepartmentId));
            });
        });
    }

    public function syncDepartmentVisibility(SocialPost $post, array $departmentIds): void
    {
        $post->departmentVisibilities()->createMany(
            collect($departmentIds)->map(fn (int $id) => ['department_id' => $id])->all()
        );
    }

    public function find(int $id, ?int $viewerId = null): ?SocialPost
    {
        return $this->baseQuery($viewerId)->find($id);
    }

    public function paginatePending(int $perPage, int $page): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->where('review_status', SocialPost::REVIEW_PENDING)
            ->orderBy('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function countPending(): int
    {
        return SocialPost::query()->where('review_status', SocialPost::REVIEW_PENDING)->count();
    }

    public function create(array $data): SocialPost
    {
        $post = SocialPost::create($data);

        return $this->find($post->id);
    }

    public function update(SocialPost $post, array $data): SocialPost
    {
        $post->update($data);

        return $this->find($post->id);
    }

    public function delete(SocialPost $post): void
    {
        $post->delete();
    }

    public function addRevision(SocialPost $post, array $data): void
    {
        $post->revisions()->create($data);
    }

    public function revisions(SocialPost $post): Collection
    {
        return $post->revisions()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();
    }

    public function setReaction(SocialPost $post, int $userId, string $type): array
    {
        $existing = $post->likes()->where('user_id', $userId)->first();

        if ($existing && $existing->reaction_type === $type) {
            $existing->delete();

            return ['action' => 'removed', 'reaction_type' => null];
        }

        if ($existing) {
            $existing->update(['reaction_type' => $type]);
        } else {
            $post->likes()->create(['user_id' => $userId, 'reaction_type' => $type]);
        }

        return ['action' => 'set', 'reaction_type' => $type];
    }

    public function myReaction(SocialPost $post, int $userId): ?string
    {
        return $post->likes()->where('user_id', $userId)->value('reaction_type');
    }

    public function reactionSummary(SocialPost $post): array
    {
        $counts = $post->likes()
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

    public function reactionUsers(SocialPost $post, ?string $type = null): Collection
    {
        $query = $post->likes()
            ->with(['user.department'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($type !== null) {
            $query->where('reaction_type', $type);
        }

        return $query->get();
    }

    public function recordView(SocialPost $post, int $userId): bool
    {
        if ((int) $post->user_id === $userId) {
            return false;
        }

        try {
            $view = $post->views()->firstOrCreate(['user_id' => $userId]);

            return $view->wasRecentlyCreated;
        } catch (\Illuminate\Database\QueryException) {
            return false;
        }
    }

    public function viewsCount(SocialPost $post): int
    {
        return $post->views()->count();
    }
}
