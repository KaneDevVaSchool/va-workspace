<?php

namespace Modules\Social\App\Services;

use App\Models\User;
use Modules\Identity\App\Services\NotificationService;
use Modules\Social\App\Models\SocialPost;
use Modules\Social\App\Models\SocialPostComment;
use Modules\Social\App\Repositories\Contracts\SocialCommentRepositoryInterface;

class SocialMentionService
{
    public function __construct(
        private readonly SocialContentSanitizer $sanitizer,
        private readonly NotificationService $notifications,
        private readonly SocialCommentRepositoryInterface $comments,
    ) {}

    /**
     * Báo cho chủ bài viết + những người đã từng bình luận gốc trong bài.
     * Loại người đã nhận thông báo @mention riêng của comment này (tránh
     * gửi 2 thông báo trùng nội dung cho cùng 1 người).
     *
     * @param  list<int>  $excludeIds  ID người đã được notifyComment() báo rồi.
     */
    public function notifyPostComment(User $actor, SocialPost $post, SocialPostComment $comment, array $excludeIds = []): void
    {
        $participantIds = array_keys($this->comments->latestCommentAtByAuthor($post->id));
        $recipientIds = array_values(array_diff(
            array_unique(array_merge([(int) $post->user_id], $participantIds)),
            $excludeIds,
        ));

        if ($recipientIds === []) {
            return;
        }

        $excerpt = $this->sanitizer->excerpt((string) ($comment->content ?? ''));

        $this->notifications->notifyUsers(
            $recipientIds,
            $actor,
            NotificationService::TYPE_SOCIAL_COMMENT,
            $actor->name.' đã bình luận về bài viết của bạn',
            $excerpt !== '' ? $excerpt : null,
            '/social?post='.$post->id.'&comment='.$comment->id,
            [
                'post_id' => $post->id,
                'comment_id' => $comment->id,
            ],
        );
    }

    public function notifyLikePost(User $actor, SocialPost $post): void
    {
        $this->notifications->notify(
            $post->user,
            $actor,
            NotificationService::TYPE_LIKE_POST,
            $actor->name.' đã thích bài viết của bạn',
            null,
            '/social?post='.$post->id,
            ['post_id' => $post->id],
        );
    }

    public function notifyLikeComment(User $actor, SocialPost $post, SocialPostComment $comment): void
    {
        $this->notifications->notify(
            $comment->user,
            $actor,
            NotificationService::TYPE_LIKE_COMMENT,
            $actor->name.' đã thích bình luận của bạn',
            null,
            '/social?post='.$post->id.'&comment='.$comment->id,
            [
                'post_id' => $post->id,
                'comment_id' => $comment->id,
            ],
        );
    }

    public function notifyShare(User $sharer, SocialPost $original): void
    {
        $this->notifications->notify(
            $original->user,
            $sharer,
            NotificationService::TYPE_SHARE_POST,
            $sharer->name.' đã chia sẻ bài viết của bạn',
            null,
            '/social?post='.$original->id,
            ['post_id' => $original->id],
        );
    }

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

    /** @return list<int> ID người đã được báo @mention (dùng để loại trùng ở notifyPostComment). */
    public function notifyComment(
        User $actor,
        SocialPost $post,
        SocialPostComment $comment,
        ?int $extraMentionId = null,
    ): array {
        $ids = $this->sanitizer->mentionIds((string) ($comment->content ?? ''));
        if ($extraMentionId !== null) {
            $ids[] = $extraMentionId;
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return [];
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

        return $ids;
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
