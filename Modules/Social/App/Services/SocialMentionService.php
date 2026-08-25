<?php

namespace Modules\Social\App\Services;

use App\Models\User;
use Modules\Identity\App\Services\NotificationService;
use Modules\Social\App\Models\SocialPost;
use Modules\Social\App\Models\SocialPostComment;

class SocialMentionService
{
    public function __construct(
        private readonly SocialContentSanitizer $sanitizer,
        private readonly NotificationService $notifications,
    ) {}

    public function notifyPost(User $actor, SocialPost $post, ?string $previousContent = null): void
    {
        $ids = $this->newMentionIds((string) ($post->content ?? ''), $previousContent);
        if ($ids === []) {
            return;
        }

        $excerpt = $this->sanitizer->excerpt((string) ($post->content ?? ''));

        $this->notifications->notifyUsers(
            $ids,
            $actor,
            NotificationService::TYPE_MENTION_POST,
            $actor->name.' đã nhắc bạn trong một bài viết',
            $excerpt !== '' ? $excerpt : null,
            '/social?post='.$post->id,
            ['post_id' => $post->id],
        );
    }

    public function notifyComment(
        User $actor,
        SocialPost $post,
        SocialPostComment $comment,
        ?int $extraMentionId = null,
    ): void {
        $ids = $this->sanitizer->mentionIds((string) ($comment->content ?? ''));
        if ($extraMentionId !== null) {
            $ids[] = $extraMentionId;
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return;
        }

        $excerpt = $this->sanitizer->excerpt((string) ($comment->content ?? ''));

        $this->notifications->notifyUsers(
            $ids,
            $actor,
            NotificationService::TYPE_MENTION_COMMENT,
            $actor->name.' đã nhắc bạn trong một bình luận',
            $excerpt !== '' ? $excerpt : null,
            '/social?post='.$post->id.'&comment='.$comment->id,
            [
                'post_id' => $post->id,
                'comment_id' => $comment->id,
            ],
        );
    }

    /**
     * @return list<int>
     */
    private function newMentionIds(string $content, ?string $previousContent): array
    {
        $current = $this->sanitizer->mentionIds($content);
        if ($previousContent === null) {
            return $current;
        }

        $previous = $this->sanitizer->mentionIds($previousContent);

        return array_values(array_diff($current, $previous));
    }
}
