<?php

namespace Modules\Social\App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Social\App\Models\SocialHashtag;
use Modules\Social\App\Models\SocialPost;
use Modules\Social\App\Repositories\Contracts\SocialPostRepositoryInterface;

class SocialHashtagService
{
    private const MAX_TAGS_PER_POST = 30;

    public function __construct(
        private readonly SocialPostRepositoryInterface $posts,
    ) {}

    /**
     * @return array<string, string> normalized => label (first seen casing)
     */
    public function extractFromHtml(string $html): array
    {
        if ($html === '') {
            return [];
        }

        $plain = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        if ($plain === '' || ! preg_match_all('/(?<![\p{L}\p{N}_\/])#([\p{L}\p{N}_]{1,64})/u', $plain, $matches)) {
            return [];
        }

        $tags = [];
        foreach ($matches[1] as $raw) {
            $normalized = self::normalize($raw);
            if ($normalized === null) {
                continue;
            }
            if (! isset($tags[$normalized])) {
                $tags[$normalized] = $raw;
            }
            if (count($tags) >= self::MAX_TAGS_PER_POST) {
                break;
            }
        }

        return $tags;
    }

    public static function normalize(string $raw): ?string
    {
        $value = mb_strtolower(trim($raw));
        if ($value === '' || mb_strlen($value) > 64) {
            return null;
        }

        if (! preg_match('/^[\p{L}\p{N}_]+$/u', $value)) {
            return null;
        }

        return $value;
    }

    public function syncForPost(SocialPost $post): void
    {
        $tags = $this->extractFromHtml((string) ($post->content ?? ''));
        $previousIds = $post->hashtags()->pluck('social_hashtags.id')->all();

        $syncIds = [];
        foreach ($tags as $normalized => $label) {
            $hashtag = SocialHashtag::query()->firstOrCreate(
                ['name' => $normalized],
                ['label' => $label, 'posts_count' => 0, 'last_used_at' => now()],
            );
            $syncIds[] = $hashtag->id;
        }

        $post->hashtags()->sync($syncIds);
        $post->unsetRelation('hashtags');

        $affected = array_values(array_unique(array_merge($previousIds, $syncIds)));
        foreach ($affected as $hashtagId) {
            $this->recount((int) $hashtagId);
        }
    }

    public function detachForPost(SocialPost $post): void
    {
        $previousIds = $post->hashtags()->pluck('social_hashtags.id')->all();
        $post->hashtags()->detach();

        foreach ($previousIds as $hashtagId) {
            $this->recount((int) $hashtagId);
        }
    }

    /**
     * @return list<array{name: string, label: string, usage_count: int, last_used_at: string|null}>
     */
    public function recentForViewer(
        User $viewer,
        ?int $departmentId,
        ?int $wallUserId,
        ?int $groupId,
        int $limit = 12,
        ?string $search = null,
    ): array {
        $limit = min(max($limit, 1), 30);
        $needle = self::normalize(ltrim(trim((string) $search), '#'));

        $builder = SocialHashtag::query()
            ->whereHas('posts', function ($postQuery) use ($viewer, $departmentId, $wallUserId, $groupId) {
                $this->posts->constrainVisibleFeed(
                    $postQuery,
                    $departmentId,
                    $wallUserId,
                    $groupId,
                    $viewer->department_id,
                );
            })
            ->withCount(['posts as usage_count' => function ($postQuery) use ($viewer, $departmentId, $wallUserId, $groupId) {
                $this->posts->constrainVisibleFeed(
                    $postQuery,
                    $departmentId,
                    $wallUserId,
                    $groupId,
                    $viewer->department_id,
                );
            }]);

        if ($needle !== null) {
            $like = addcslashes($needle, '%_\\');
            $builder->where('name', 'like', '%'.$like.'%')
                ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$like.'%'])
                ->orderByDesc('usage_count')
                ->orderBy('name');
        } else {
            $builder->orderByDesc('last_used_at')
                ->orderByDesc('usage_count');
        }

        return $builder
            ->limit($limit)
            ->get()
            ->map(fn (SocialHashtag $hashtag) => [
                'name' => $hashtag->name,
                'label' => $hashtag->label,
                'usage_count' => (int) $hashtag->usage_count,
                'last_used_at' => $hashtag->last_used_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function recount(int $hashtagId): void
    {
        $count = (int) DB::table('social_hashtag_post')->where('hashtag_id', $hashtagId)->count();

        if ($count === 0) {
            SocialHashtag::query()->whereKey($hashtagId)->delete();

            return;
        }

        $lastUsed = DB::table('social_hashtag_post')
            ->where('hashtag_id', $hashtagId)
            ->max('created_at');

        SocialHashtag::query()->whereKey($hashtagId)->update([
            'posts_count' => $count,
            'last_used_at' => $lastUsed,
        ]);
    }
}
