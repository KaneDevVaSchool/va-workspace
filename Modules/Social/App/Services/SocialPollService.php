<?php

namespace Modules\Social\App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Social\App\Models\SocialPoll;
use Modules\Social\App\Models\SocialPollVote;
use Modules\Social\App\Models\SocialPost;
use Modules\Social\App\Repositories\Contracts\SocialPollRepositoryInterface;

class SocialPollService
{
    public function __construct(
        private readonly SocialPollRepositoryInterface $polls,
    ) {}

    /**
     * @param  array{options?: mixed, title?: mixed, content?: mixed, image?: mixed, allow_multiple?: mixed, ends_at?: mixed}  $data
     */
    public function createForPost(SocialPost $post, array $data): SocialPoll
    {
        $title = $this->plainText($data['title'] ?? null, 200);
        $content = $this->plainText($data['content'] ?? null, 2000);
        $imagePath = $this->storeImage($post->id, $data['image'] ?? null);

        return $this->polls->createForPost($post, [
            'title' => $title,
            'content' => $content,
            'image_path' => $imagePath,
            'options' => $this->normalizedOptions($data['options'] ?? []),
            'allow_multiple' => (bool) ($data['allow_multiple'] ?? false),
            'ends_at' => $data['ends_at'] ?? null,
        ]);
    }

    private function plainText(mixed $value, int $max): ?string
    {
        $text = trim(strip_tags((string) $value));
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $max);
    }

    private function storeImage(int $postId, mixed $file): ?string
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        return $file->store('social/'.$postId.'/poll', 'public');
    }

    public function deleteImage(?SocialPoll $poll): void
    {
        if ($poll?->image_path) {
            Storage::disk('public')->delete($poll->image_path);
        }
    }

    /** @return list<string> */
    private function normalizedOptions(mixed $options): array
    {
        $labels = collect(is_array($options) ? $options : [])
            ->map(fn ($label) => trim((string) $label))
            ->filter(fn ($label) => $label !== '')
            ->values();

        if ($labels->count() < 2) {
            throw ValidationException::withMessages([
                'poll.options' => ['Bình chọn cần ít nhất 2 phương án.'],
            ]);
        }

        if ($labels->count() > 10) {
            throw ValidationException::withMessages([
                'poll.options' => ['Bình chọn tối đa 10 phương án.'],
            ]);
        }

        return $labels->all();
    }

    public function vote(SocialPost $post, User $user, int $optionId): array
    {
        $poll = $this->pollOf($post);

        if ($poll->isEnded()) {
            throw ValidationException::withMessages([
                'option_id' => ['Bình chọn đã kết thúc.'],
            ]);
        }

        $option = $poll->options->firstWhere('id', $optionId);
        if ($option === null) {
            throw ValidationException::withMessages([
                'option_id' => ['Phương án không thuộc bình chọn này.'],
            ]);
        }

        DB::transaction(function () use ($poll, $user, $optionId) {
            $locked = SocialPoll::query()->whereKey($poll->id)->lockForUpdate()->firstOrFail();

            if ($this->polls->hasVote($locked, $user->id, $optionId)) {
                $this->polls->removeVote($locked, $user->id, $optionId);

                return;
            }

            if ($locked->allow_multiple) {
                $this->polls->addVote($locked, $user->id, $optionId);
            } else {
                $this->polls->replaceVotes($locked, $user->id, $optionId);
            }
        });

        return ['poll' => $this->present($this->polls->reload($poll), $user, $this->isAuthor($post, $user))];
    }

    public function close(SocialPost $post, User $actor): array
    {
        $poll = $this->pollOf($post);

        if (! $this->isAuthor($post, $actor)) {
            throw ValidationException::withMessages([
                'poll' => ['Chỉ người tạo bài viết mới được đóng bình chọn.'],
            ]);
        }

        if ($poll->isEnded()) {
            throw ValidationException::withMessages([
                'poll' => ['Bình chọn đã kết thúc.'],
            ]);
        }

        return ['poll' => $this->present($this->polls->close($poll), $actor, true)];
    }

    public function voters(SocialPost $post, User $viewer, ?int $optionId = null): array
    {
        $poll = $this->pollOf($post);
        $isAuthor = $this->isAuthor($post, $viewer);
        $presented = $this->present($poll, $viewer, $isAuthor);

        if (! $presented['show_results']) {
            throw ValidationException::withMessages([
                'poll' => ['Hãy bình chọn trước khi xem danh sách người đã chọn.'],
            ]);
        }

        if ($optionId !== null && $poll->options->firstWhere('id', $optionId) === null) {
            throw ValidationException::withMessages([
                'option_id' => ['Phương án không thuộc bình chọn này.'],
            ]);
        }

        return [
            'users' => $this->polls->voters($poll, $optionId)
                ->map(fn (SocialPollVote $vote) => $this->presentVoter($vote))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    public function present(?SocialPoll $poll, User $viewer, bool $isAuthor): ?array
    {
        if ($poll === null) {
            return null;
        }

        $ended = $poll->isEnded();
        $myOptionIds = $this->polls->myOptionIds($poll, $viewer->id);
        $showResults = $ended || $myOptionIds !== [];
        $totalVotes = (int) $poll->options->sum(fn ($option) => (int) ($option->votes_count ?? 0));

        return [
            'id' => $poll->id,
            'title' => $poll->title,
            'content' => $poll->content,
            'image_url' => $poll->image_path ? Storage::disk('public')->url($poll->image_path) : null,
            'allow_multiple' => $poll->allow_multiple,
            'ends_at' => $poll->ends_at?->toIso8601String(),
            'is_closed' => $poll->is_closed,
            'is_ended' => $ended,
            'show_results' => $showResults,
            'total_votes' => $showResults ? $totalVotes : null,
            'my_option_ids' => $myOptionIds,
            'can_vote' => ! $ended,
            'can_close' => $isAuthor && ! $ended,
            'options' => $poll->options->map(function ($option) use ($showResults, $totalVotes) {
                $count = (int) ($option->votes_count ?? 0);

                return [
                    'id' => $option->id,
                    'label' => $option->label,
                    'votes_count' => $showResults ? $count : null,
                    'percent' => $showResults && $totalVotes > 0 ? (int) round(($count / $totalVotes) * 100) : ($showResults ? 0 : null),
                ];
            })->values()->all(),
        ];
    }

    private function pollOf(SocialPost $post): SocialPoll
    {
        $poll = $post->poll;
        if ($poll === null) {
            throw ValidationException::withMessages([
                'poll' => ['Bài viết này không có bình chọn.'],
            ]);
        }

        return $this->polls->reload($poll);
    }

    private function isAuthor(SocialPost $post, User $user): bool
    {
        return (int) $post->user_id === (int) $user->id;
    }

    /** @return array{option_id: int, user: array{id: int, name: string, avatar_url: mixed, department: string|null}}|null */
    private function presentVoter(SocialPollVote $vote): ?array
    {
        if ($vote->user === null) {
            return null;
        }

        return [
            'option_id' => $vote->option_id,
            'user' => [
                'id' => $vote->user->id,
                'name' => $vote->user->name,
                'avatar_url' => $vote->user->avatar_url,
                'department' => $vote->user->department?->name,
            ],
        ];
    }
}
