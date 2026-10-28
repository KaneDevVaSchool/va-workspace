<?php

namespace Modules\Social\App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Chat\App\Models\Conversation;
use Modules\Social\App\Models\SocialStory;
use Modules\Social\App\Models\SocialStoryView;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SocialStoryService
{
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    private const VIDEO_MIMES = ['video/mp4', 'video/webm', 'video/quicktime'];

    public function rings(User $actor): array
    {
        $stories = SocialStory::query()
            ->with('user')
            ->where('expires_at', '>', now())
            ->orderBy('created_at')
            ->get();

        $viewedIds = SocialStoryView::query()
            ->where('viewer_id', $actor->id)
            ->whereIn('story_id', $stories->pluck('id'))
            ->pluck('story_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $grouped = $stories->groupBy('user_id');
        $mineStories = $grouped->get($actor->id, collect());
        $contacts = $this->privateChatContacts($actor);
        $ringsByUserId = [];

        foreach ($grouped as $userId => $items) {
            if ((int) $userId === (int) $actor->id) {
                continue;
            }
            $user = $items->first()?->user;
            if (! $user || ! $user->isActive()) {
                continue;
            }
            $presented = $items->map(fn (SocialStory $story) => $this->present($story, $viewedIds))->values()->all();
            $ringsByUserId[(int) $userId] = [
                'user' => $this->presentUser($user),
                'has_stories' => true,
                'unseen' => collect($presented)->contains(fn (array $story) => ! $story['viewed']),
                'stories' => $presented,
                'latest_at' => $items->max('created_at')?->toIso8601String(),
                'contact_rank' => $contacts['rank'][(int) $userId] ?? null,
            ];
        }

        foreach ($contacts['users'] as $rank => $user) {
            $userId = (int) $user->id;
            if ($userId === (int) $actor->id || isset($ringsByUserId[$userId])) {
                continue;
            }
            $ringsByUserId[$userId] = [
                'user' => $this->presentUser($user),
                'has_stories' => false,
                'unseen' => false,
                'stories' => [],
                'latest_at' => null,
                'contact_rank' => $rank,
            ];
        }

        $rings = array_values($ringsByUserId);
        usort($rings, fn (array $left, array $right) => $this->compareRings($left, $right));

        $minePresented = $mineStories->map(fn (SocialStory $story) => $this->present($story, $viewedIds))->values()->all();

        return [
            'mine' => [
                'user' => $this->presentUser($actor),
                'has_stories' => $minePresented !== [],
                'unseen' => false,
                'stories' => $minePresented,
            ],
            'rings' => array_map(fn (array $ring) => $this->publicRing($ring), $rings),
        ];
    }

    public function store(User $actor, UploadedFile $file): array
    {
        $mime = (string) $file->getMimeType();
        $kind = null;
        if (in_array($mime, self::IMAGE_MIMES, true)) {
            $kind = 'image';
        } elseif (in_array($mime, self::VIDEO_MIMES, true)) {
            $kind = 'video';
        }

        if ($kind === null) {
            throw ValidationException::withMessages([
                'file' => ['Story chỉ nhận ảnh JPG, PNG, WEBP, GIF hoặc video ngắn MP4, WEBM.'],
            ]);
        }

        if ($kind === 'video' && ($file->getSize() ?: 0) > 20 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'file' => ['Video story không được vượt quá 20MB.'],
            ]);
        }

        $path = $file->store('social/stories/'.$actor->id, 'local');
        $story = SocialStory::query()->create([
            'user_id' => $actor->id,
            'media_path' => $path,
            'disk' => 'local',
            'media_kind' => $kind,
            'expires_at' => now()->addDay(),
        ]);

        return $this->present($story, []);
    }

    public function markViewed(User $actor, int $storyId): void
    {
        $story = $this->activeStory($storyId);
        if ((int) $story->user_id === (int) $actor->id) {
            return;
        }

        SocialStoryView::query()->firstOrCreate(
            ['story_id' => $story->id, 'viewer_id' => $actor->id],
            ['viewed_at' => now()],
        );
    }

    public function destroy(User $actor, int $storyId): void
    {
        $story = SocialStory::query()->find($storyId);
        if (! $story) {
            return;
        }
        if ((int) $story->user_id !== (int) $actor->id) {
            throw new AccessDeniedHttpException('Chỉ xoá được story của mình.');
        }

        Storage::disk($story->disk ?: 'local')->delete($story->media_path);
        $story->delete();
    }

    public function media(User $actor, int $storyId): StreamedResponse
    {
        $story = $this->activeStory($storyId);
        $disk = $story->disk ?: 'local';
        if (! Storage::disk($disk)->exists($story->media_path)) {
            abort(404);
        }

        return Storage::disk($disk)->response($story->media_path);
    }

    private function activeStory(int $storyId): SocialStory
    {
        $story = SocialStory::query()->where('expires_at', '>', now())->find($storyId);
        if (! $story) {
            abort(404);
        }

        return $story;
    }

    /**
     * @param  list<int>  $viewedIds
     * @return array<string, mixed>
     */
    private function present(SocialStory $story, array $viewedIds): array
    {
        return [
            'id' => $story->id,
            'media_kind' => $story->media_kind,
            'media_url' => url('/api/social/stories/'.$story->id.'/media'),
            'created_at' => $story->created_at?->toIso8601String(),
            'expires_at' => $story->expires_at?->toIso8601String(),
            'viewed' => in_array((int) $story->id, $viewedIds, true) || false,
        ];
    }

    /**
     * @return array{id: int, name: string, avatar_url: string|null}
     */
    private function presentUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatar_url,
        ];
    }

    /**
     * @return array{users: Collection<int, User>, rank: array<int, int>}
     */
    private function privateChatContacts(User $actor): array
    {
        $conversations = Conversation::query()
            ->where('type', Conversation::TYPE_PRIVATE)
            ->whereHas('members', fn ($query) => $query->where('user_id', $actor->id))
            ->with(['members.user'])
            ->orderByDesc('updated_at')
            ->limit(80)
            ->get();

        $users = collect();
        $rank = [];
        $nextRank = 0;

        foreach ($conversations as $conversation) {
            $otherMember = $conversation->members->first(
                fn ($member) => (int) $member->user_id !== (int) $actor->id,
            );
            $user = $otherMember?->user;
            if (! $user || ! $user->isActive()) {
                continue;
            }
            $userId = (int) $user->id;
            if (isset($rank[$userId])) {
                continue;
            }
            $rank[$userId] = $nextRank;
            $users->push($user);
            $nextRank++;
        }

        return ['users' => $users, 'rank' => $rank];
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function compareRings(array $left, array $right): int
    {
        $leftHas = ! empty($left['has_stories']);
        $rightHas = ! empty($right['has_stories']);

        if ($leftHas && $rightHas) {
            $unseen = (int) $right['unseen'] <=> (int) $left['unseen'];
            if ($unseen !== 0) {
                return $unseen;
            }

            return strcmp((string) ($right['latest_at'] ?? ''), (string) ($left['latest_at'] ?? ''));
        }

        if ($leftHas !== $rightHas) {
            return $leftHas ? -1 : 1;
        }

        return ((int) ($left['contact_rank'] ?? PHP_INT_MAX)) <=> ((int) ($right['contact_rank'] ?? PHP_INT_MAX));
    }

    /**
     * @param  array<string, mixed>  $ring
     * @return array<string, mixed>
     */
    private function publicRing(array $ring): array
    {
        unset($ring['contact_rank']);

        return $ring;
    }
}
