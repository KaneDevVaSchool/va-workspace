<?php

namespace Modules\Social\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Social\App\Models\SocialPoll;
use Modules\Social\App\Models\SocialPollVote;
use Modules\Social\App\Models\SocialPost;
use Modules\Social\App\Repositories\Contracts\SocialPollRepositoryInterface;

class SocialPollRepository implements SocialPollRepositoryInterface
{
    public function createForPost(SocialPost $post, array $data): SocialPoll
    {
        $poll = SocialPoll::create([
            'post_id' => $post->id,
            'title' => $data['title'] ?? null,
            'content' => $data['content'] ?? null,
            'image_path' => $data['image_path'] ?? null,
            'allow_multiple' => (bool) ($data['allow_multiple'] ?? false),
            'ends_at' => $data['ends_at'] ?? null,
        ]);

        foreach (array_values($data['options']) as $index => $label) {
            $poll->options()->create([
                'label' => $label,
                'position' => $index,
            ]);
        }

        return $this->reload($poll);
    }

    public function reload(SocialPoll $poll): SocialPoll
    {
        return SocialPoll::query()
            ->with($this->optionEagerLoad())
            ->findOrFail($poll->id);
    }

    /** @return array<string, \Closure> */
    private function optionEagerLoad(): array
    {
        return [
            'options' => fn ($query) => $query
                ->withCount('votes')
                ->orderBy('position')
                ->orderBy('id'),
        ];
    }

    public function myOptionIds(SocialPoll $poll, int $userId): array
    {
        return $poll->votes()
            ->where('user_id', $userId)
            ->pluck('option_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function hasVote(SocialPoll $poll, int $userId, int $optionId): bool
    {
        return $poll->votes()
            ->where('user_id', $userId)
            ->where('option_id', $optionId)
            ->exists();
    }

    public function addVote(SocialPoll $poll, int $userId, int $optionId): void
    {
        $poll->votes()->create([
            'option_id' => $optionId,
            'user_id' => $userId,
        ]);
    }

    public function removeVote(SocialPoll $poll, int $userId, int $optionId): void
    {
        $poll->votes()
            ->where('user_id', $userId)
            ->where('option_id', $optionId)
            ->delete();
    }

    public function replaceVotes(SocialPoll $poll, int $userId, int $optionId): void
    {
        $poll->votes()->where('user_id', $userId)->delete();
        $this->addVote($poll, $userId, $optionId);
    }

    public function close(SocialPoll $poll): SocialPoll
    {
        $poll->update(['is_closed' => true]);

        return $this->reload($poll);
    }

    public function voters(SocialPoll $poll, ?int $optionId = null): Collection
    {
        $query = SocialPollVote::query()
            ->where('poll_id', $poll->id)
            ->with(['user.department'])
            ->orderByDesc('id');

        if ($optionId !== null) {
            $query->where('option_id', $optionId);
        }

        return $query->get();
    }
}
