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
    private function baseQuery()
    {
        return SocialPost::query()
            ->with([
                'user.department',
                'pinnedBy',
                'sharedFrom.user',
                'wallUser.department',
                'poll.options' => fn ($query) => $query
                    ->withCount('votes')
                    ->orderBy('position')
                    ->orderBy('id'),
            ])
            ->withCount(['comments']);
    }

    public function paginate(int $perPage, int $page, string $scope = 'all', ?int $userId = null, ?int $departmentId = null, ?int $wallUserId = null): LengthAwarePaginator
    {
        $query = $this->baseQuery();
        $this->applyWall($query, $departmentId, $wallUserId);

        if ($userId !== null && $scope === 'mine') {
            $query->where('user_id', $userId);
        }

        if ($userId !== null && $scope === 'reacted') {
            $query->whereHas('likes', fn ($likes) => $likes->where('user_id', $userId));
        }

        return $query
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
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
    ): LengthAwarePaginator {
        $query = $this->baseQuery()
            ->where('is_pinned', true)
            ->where('pin_scope', $scope);

        $this->applyWall($query, $departmentId, $wallUserId);
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

    private function applyWall($query, ?int $departmentId, ?int $wallUserId): void
    {
        if ($wallUserId !== null) {
            $query->where('wall_user_id', $wallUserId)->whereNull('department_id');

            return;
        }

        if ($departmentId !== null) {
            $query->where('department_id', $departmentId)->whereNull('wall_user_id');

            return;
        }

        $query->whereNull('department_id')->whereNull('wall_user_id');
    }

    public function find(int $id): ?SocialPost
    {
        return $this->baseQuery()->find($id);
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
}
