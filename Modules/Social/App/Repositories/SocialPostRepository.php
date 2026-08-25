<?php

namespace Modules\Social\App\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Social\App\Models\SocialPost;
use Modules\Social\App\Models\SocialPostLike;
use Modules\Social\App\Repositories\Contracts\SocialPostRepositoryInterface;

class SocialPostRepository implements SocialPostRepositoryInterface
{
    private function baseQuery()
    {
        return SocialPost::query()
            ->with(['user', 'pinnedBy', 'sharedFrom.user'])
            ->withCount(['comments']);
    }

    public function paginate(int $perPage, int $page): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function pinned(int $limit): Collection
    {
        return $this->baseQuery()
            ->where('is_pinned', true)
            ->orderByDesc('pinned_at')
            ->limit($limit)
            ->get();
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
}
