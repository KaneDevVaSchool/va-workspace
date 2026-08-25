<?php

namespace Modules\Social\App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;
use Modules\Identity\App\Services\PermissionService;
use Modules\Social\App\Models\SocialCommentLike;
use Modules\Social\App\Models\SocialPost;
use Modules\Social\App\Models\SocialPostComment;
use Modules\Social\App\Models\SocialPostLike;
use Modules\Social\App\Repositories\Contracts\SocialCommentRepositoryInterface;

class SocialCommentService
{
    public function __construct(
        private readonly SocialCommentRepositoryInterface $comments,
        private readonly PermissionService $permissions,
        private readonly SocialContentSanitizer $sanitizer,
        private readonly UserRepositoryInterface $users,
        private readonly SocialMentionService $mentions,
    ) {}

    public function listForPost(SocialPost $post, User $viewer): array
    {
        return $this->comments->forPost($post->id)
            ->map(fn (SocialPostComment $comment) => $this->present($comment, $viewer))
            ->values()
            ->all();
    }

    public function searchMentions(User $viewer, string $query): array
    {
        return $this->users->searchActiveByName($query, 8, $viewer->id)
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar_url,
                'department' => $user->department?->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  UploadedFile[]  $files
     */
    public function create(
        User $author,
        SocialPost $post,
        ?string $content,
        ?int $parentCommentId = null,
        ?int $mentionedUserId = null,
        array $files = [],
    ): array {
        $resolvedParentId = null;
        $resolvedMentionId = $mentionedUserId;

        if ($parentCommentId !== null) {
            $parent = $this->comments->find($parentCommentId);

            if (! $parent || (int) $parent->post_id !== (int) $post->id) {
                throw ValidationException::withMessages([
                    'parent_comment_id' => ['Bình luận gốc không hợp lệ.'],
                ]);
            }

            $depth = $this->commentDepth($parent);

            if ($depth >= 2) {
                $resolvedParentId = $parent->parent_comment_id;
                $resolvedMentionId = $resolvedMentionId ?? $parent->user_id;
            } else {
                $resolvedParentId = $parent->id;
                if ($depth >= 1) {
                    $resolvedMentionId = $resolvedMentionId ?? $parent->user_id;
                }
            }
        }

        $clean = $this->sanitizeContent($content ?? '');
        $plain = trim(html_entity_decode(strip_tags($clean), ENT_QUOTES, 'UTF-8'));

        if ($plain === '' && $files === []) {
            throw ValidationException::withMessages([
                'content' => ['Bình luận phải có nội dung hoặc ít nhất 1 tệp đính kèm.'],
            ]);
        }

        $comment = $this->comments->create([
            'post_id' => $post->id,
            'parent_comment_id' => $resolvedParentId,
            'user_id' => $author->id,
            'mentioned_user_id' => $resolvedMentionId,
            'content' => $clean,
        ]);

        if ($files !== []) {
            $comment = $this->comments->update($comment, [
                'attachments' => $this->storeAttachments($comment->id, $files),
            ]);
        }

        $this->mentions->notifyComment($author, $post, $comment, $resolvedMentionId);

        return [
            'comment' => $this->present($comment, $author),
            'comments_count' => $this->comments->countForPost($post->id),
        ];
    }

    public function setReaction(SocialPostComment $comment, User $user, string $type): array
    {
        $result = $this->comments->setReaction($comment, $user->id, $type);

        return [
            'reactions' => $this->comments->reactionSummary($comment),
            'my_reaction' => $result['reaction_type'],
        ];
    }

    public function reactionUsers(SocialPostComment $comment, ?string $type = null): array
    {
        return [
            'users' => $this->comments->reactionUsers($comment, $type)
                ->map(fn (SocialCommentLike $like) => $this->presentReactionUser($like->user, $like->reaction_type))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /** @return array{type: string, user: array{id: int, name: string, avatar_url: mixed, department: string|null}}|null */
    private function presentReactionUser(?User $user, string $type): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'type' => $type,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar_url,
                'department' => $user->department?->name,
            ],
        ];
    }

    public function canDelete(SocialPostComment $comment, User $actor): bool
    {
        if ((int) $comment->user_id === (int) $actor->id) {
            return true;
        }

        $authorDepartmentId = $comment->user->department_id;

        return $authorDepartmentId !== null
            && $this->permissions->allows($actor, 'social.moderate', 'department', $authorDepartmentId);
    }

    public function delete(SocialPostComment $comment): int
    {
        $postId = $comment->post_id;
        $comment->loadMissing(['replies.replies']);

        $this->deleteTreeAttachments($comment);
        $this->comments->delete($comment);

        return $this->comments->countForPost($postId);
    }

    /** 0 = gốc, 1 = trả lời, 2 = trả lời lồng. */
    private function commentDepth(SocialPostComment $comment): int
    {
        if ($comment->parent_comment_id === null) {
            return 0;
        }

        $parent = $this->comments->find($comment->parent_comment_id);

        if (! $parent || $parent->parent_comment_id === null) {
            return 1;
        }

        return 2;
    }

    private function deleteTreeAttachments(SocialPostComment $comment): void
    {
        $this->deleteStoredAttachments($comment->attachments ?? []);

        if (! $comment->relationLoaded('replies')) {
            return;
        }

        foreach ($comment->replies as $reply) {
            $this->deleteTreeAttachments($reply);
        }
    }

    public function present(SocialPostComment $comment, User $viewer): array
    {
        $reactions = $this->reactionPayload($comment, $viewer);

        return [
            'id' => $comment->id,
            'parent_comment_id' => $comment->parent_comment_id,
            'content' => $comment->content,
            'attachments' => collect($comment->attachments ?? [])->map(fn (array $a) => [
                'type' => $a['type'],
                'name' => $a['name'],
                'size' => $a['size'],
                'url' => Storage::disk('public')->url($a['path']),
            ])->all(),
            'author' => [
                'id' => $comment->user->id,
                'name' => $comment->user->name,
                'avatar_url' => $comment->user->avatar_url,
            ],
            'mentioned_user' => $comment->mentionedUser ? [
                'id' => $comment->mentionedUser->id,
                'name' => $comment->mentionedUser->name,
            ] : null,
            'reactions' => $reactions['reactions'],
            'my_reaction' => $reactions['my_reaction'],
            'replies' => $comment->relationLoaded('replies')
                ? $comment->replies->map(fn (SocialPostComment $reply) => $this->present($reply, $viewer))->values()->all()
                : [],
            'created_at' => $comment->created_at?->toIso8601String(),
        ];
    }

    /** @return array{reactions: array<string, int>, my_reaction: string|null} */
    private function reactionPayload(SocialPostComment $comment, User $viewer): array
    {
        $likes = $comment->relationLoaded('likes')
            ? $comment->likes
            : $comment->likes()->get();

        $summary = [];
        foreach (SocialPostLike::REACTION_TYPES as $type) {
            $summary[$type] = 0;
        }

        $myReaction = null;
        foreach ($likes as $like) {
            if (! isset($summary[$like->reaction_type])) {
                continue;
            }
            $summary[$like->reaction_type]++;
            if ((int) $like->user_id === (int) $viewer->id) {
                $myReaction = $like->reaction_type;
            }
        }
        $summary['total'] = 0;
        foreach (SocialPostLike::REACTION_TYPES as $type) {
            $summary['total'] += $summary[$type];
        }

        return [
            'reactions' => $summary,
            'my_reaction' => $myReaction,
        ];
    }

    private function sanitizeContent(string $content): string
    {
        return trim($this->sanitizer->sanitize($content));
    }

    /** @param UploadedFile[] $files */
    private function storeAttachments(int $commentId, array $files): array
    {
        $imageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        return collect($files)->map(function (UploadedFile $file) use ($commentId, $imageMimes) {
            $path = $file->store('social/comments/'.$commentId, 'public');

            return [
                'type' => in_array($file->getMimeType(), $imageMimes, true) ? 'image' : 'file',
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'path' => $path,
            ];
        })->all();
    }

    private function deleteStoredAttachments(array $attachments): void
    {
        foreach ($attachments as $attachment) {
            if (isset($attachment['path'])) {
                Storage::disk('public')->delete($attachment['path']);
            }
        }
    }
}
