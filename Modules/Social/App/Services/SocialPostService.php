<?php

namespace Modules\Social\App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Identity\App\Services\PermissionService;
use Modules\Social\App\Models\SocialPost;
use Modules\Social\App\Repositories\Contracts\SocialPostRepositoryInterface;

class SocialPostService
{
    public function __construct(
        private readonly SocialPostRepositoryInterface $posts,
        private readonly PermissionService $permissions,
        private readonly SocialContentSanitizer $sanitizer,
    ) {}

    public function listFeed(User $viewer, int $perPage, int $page): array
    {
        $paginator = $this->posts->paginate($perPage, $page);

        return [
            'posts' => collect($paginator->items())
                ->map(fn (SocialPost $post) => $this->present($post, $viewer))
                ->values(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }

    public function pinned(User $viewer, int $limit = 5): array
    {
        return $this->posts->pinned($limit)
            ->map(fn (SocialPost $post) => $this->present($post, $viewer))
            ->values()
            ->all();
    }

    /** Quyền kiểm duyệt của $viewer đối với bài viết của tác giả thuộc $authorDepartmentId. */
    private function canModerate(User $viewer, ?int $authorDepartmentId): bool
    {
        return $authorDepartmentId !== null
            && $this->permissions->allows($viewer, 'social.moderate', 'department', $authorDepartmentId);
    }

    private function canPin(User $viewer): bool
    {
        return $this->permissions->allows($viewer, 'social.pin', 'department', $viewer->department_id);
    }

    public function create(User $author, array $data, array $files = []): SocialPost
    {
        $post = $this->posts->create([
            'user_id' => $author->id,
            'content' => isset($data['content']) ? $this->sanitizeContent($data['content']) : null,
        ]);

        if ($files !== []) {
            $post = $this->posts->update($post, [
                'attachments' => $this->storeAttachments($post->id, $files),
            ]);
        }

        return $post;
    }

    public function update(SocialPost $post, User $editor, array $data): SocialPost
    {
        if ((int) $post->user_id !== (int) $editor->id) {
            throw ValidationException::withMessages([
                'content' => ['Chỉ tác giả mới được sửa bài viết này.'],
            ]);
        }

        return $this->posts->update($post, [
            'content' => $this->sanitizeContent($data['content']),
        ]);
    }

    public function delete(SocialPost $post): void
    {
        foreach ($post->attachments ?? [] as $attachment) {
            if (isset($attachment['path'])) {
                Storage::disk('public')->delete($attachment['path']);
            }
        }

        $this->posts->delete($post);
    }

    public function share(User $sharer, SocialPost $original, ?string $caption): SocialPost
    {
        return $this->posts->create([
            'user_id' => $sharer->id,
            'content' => $caption !== null ? $this->sanitizeContent($caption) : null,
            'shared_from_post_id' => $original->id,
        ]);
    }

    public function setReaction(SocialPost $post, User $user, string $type): array
    {
        $result = $this->posts->setReaction($post, $user->id, $type);

        return [
            'reactions' => $this->posts->reactionSummary($post),
            'my_reaction' => $result['reaction_type'],
        ];
    }

    public function pin(SocialPost $post, User $actor): SocialPost
    {
        return $this->posts->update($post, [
            'is_pinned' => true,
            'pinned_by' => $actor->id,
            'pinned_at' => now(),
        ]);
    }

    public function unpin(SocialPost $post): SocialPost
    {
        return $this->posts->update($post, [
            'is_pinned' => false,
            'pinned_by' => null,
            'pinned_at' => null,
        ]);
    }

    public function present(SocialPost $post, User $viewer): array
    {
        return [
            'id' => $post->id,
            'content' => $post->content,
            'attachments' => collect($post->attachments ?? [])->map(fn (array $a) => [
                'type' => $a['type'],
                'name' => $a['name'],
                'size' => $a['size'],
                'url' => Storage::disk('public')->url($a['path']),
            ])->all(),
            'author' => [
                'id' => $post->user->id,
                'name' => $post->user->name,
                'avatar_url' => $post->user->avatar_url,
                'department' => $post->user->department?->name,
            ],
            'is_pinned' => $post->is_pinned,
            'pinned_by' => $post->pinnedBy?->name,
            'shared_from' => $post->sharedFrom ? [
                'id' => $post->sharedFrom->id,
                'content' => $post->sharedFrom->content,
                'author' => [
                    'id' => $post->sharedFrom->user->id,
                    'name' => $post->sharedFrom->user->name,
                    'avatar_url' => $post->sharedFrom->user->avatar_url,
                ],
            ] : null,
            'reactions' => $this->posts->reactionSummary($post),
            'my_reaction' => $this->posts->myReaction($post, $viewer->id),
            'comments_count' => $post->comments_count,
            'can_edit' => (int) $post->user_id === (int) $viewer->id,
            'can_delete' => (int) $post->user_id === (int) $viewer->id
                || $this->canModerate($viewer, $post->user->department_id),
            'can_pin' => $this->canPin($viewer),
            'created_at' => $post->created_at?->toIso8601String(),
        ];
    }

    private function sanitizeContent(string $content): string
    {
        return trim($this->sanitizer->sanitize($content));
    }

    /** @param UploadedFile[] $files */
    private function storeAttachments(int $postId, array $files): array
    {
        $imageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        return collect($files)->map(function (UploadedFile $file) use ($postId, $imageMimes) {
            $path = $file->store('social/'.$postId, 'public');

            return [
                'type' => in_array($file->getMimeType(), $imageMimes, true) ? 'image' : 'file',
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'path' => $path,
            ];
        })->all();
    }
}
