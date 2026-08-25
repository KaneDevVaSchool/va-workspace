<?php

namespace Modules\Social\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Social\App\Models\SocialPoll;
use Modules\Social\App\Models\SocialPost;

interface SocialPollRepositoryInterface
{
    /**
     * @param  array{options: list<string>, title?: string|null, content?: string|null, image_path?: string|null, allow_multiple?: bool, ends_at?: string|null}  $data
     */
    public function createForPost(SocialPost $post, array $data): SocialPoll;

    public function reload(SocialPoll $poll): SocialPoll;

    /** @return list<int> */
    public function myOptionIds(SocialPoll $poll, int $userId): array;

    public function hasVote(SocialPoll $poll, int $userId, int $optionId): bool;

    public function addVote(SocialPoll $poll, int $userId, int $optionId): void;

    public function removeVote(SocialPoll $poll, int $userId, int $optionId): void;

    public function replaceVotes(SocialPoll $poll, int $userId, int $optionId): void;

    public function close(SocialPoll $poll): SocialPoll;

    /**
     * @return Collection<int, \Modules\Social\App\Models\SocialPollVote>
     */
    public function voters(SocialPoll $poll, ?int $optionId = null): Collection;
}
