<?php

namespace Modules\Social\App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;
use Modules\Identity\App\Services\PermissionService;
use Modules\Identity\App\Services\ViewAsService;
use Modules\Social\App\Models\SocialGroupMember;
use Modules\Social\App\Models\SocialHashtag;
use Modules\Social\App\Models\SocialPost;
use Modules\Social\App\Models\SocialPostDepartmentVisibility;
use Modules\Social\App\Models\SocialPostLike;
use Modules\Social\App\Repositories\Contracts\SocialGroupRepositoryInterface;
use Modules\Social\App\Repositories\Contracts\SocialPostRepositoryInterface;

class SocialPostService
{
    public const PIN_SCOPE_COMPANY = 'company';

    public const PIN_SCOPE_SYSTEM = 'system';

    public const FEED_SCOPE_ALL = 'all';

    public const FEED_SCOPE_MINE = 'mine';

    public const FEED_SCOPE_REACTED = 'reacted';

    public const POST_SCOPE_COMPANY = 'company';

    public const POST_SCOPE_DEPARTMENT = 'department';

    public const POST_SCOPE_PERSONAL = 'personal';

    public const POST_SCOPE_GROUP = 'group';

    public const DEPT_VISIBILITY_ALL = SocialPost::DEPARTMENT_VISIBILITY_ALL;

    public const DEPT_VISIBILITY_INCLUDE = SocialPost::DEPARTMENT_VISIBILITY_INCLUDE;

    public const DEPT_VISIBILITY_EXCLUDE = SocialPost::DEPARTMENT_VISIBILITY_EXCLUDE;

    public function __construct(
        private readonly SocialPostRepositoryInterface $posts,
        private readonly PermissionService $permissions,
        private readonly SocialContentSanitizer $sanitizer,
        private readonly ViewAsService $viewAs,
        private readonly UserRepositoryInterface $users,
        private readonly SocialPollService $polls,
        private readonly SocialMentionService $mentions,
        private readonly SocialGroupRepositoryInterface $groups,
        private readonly SocialHashtagService $hashtags,
    ) {}

    public function listFeed(User $viewer, int $perPage, int $page, string $scope = self::FEED_SCOPE_ALL, ?int $departmentId = null, ?int $wallUserId = null, ?int $groupId = null, ?string $hashtag = null): array
    {
        $scope = $this->normalizeFeedScope($scope);
        $paginator = $this->posts->paginate($perPage, $page, $scope, $viewer->id, $departmentId, $wallUserId, $groupId, $viewer->department_id, $hashtag);

        return [
            'posts' => collect($paginator->items())
                ->map(fn (SocialPost $post) => $this->present($post, $viewer))
                ->values(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }

    public function profileStats(User $user): array
    {
        return $this->posts->profileStats($user->id);
    }

    private function normalizeFeedScope(string $scope): string
    {
        return in_array($scope, [self::FEED_SCOPE_ALL, self::FEED_SCOPE_MINE, self::FEED_SCOPE_REACTED], true)
            ? $scope
            : self::FEED_SCOPE_ALL;
    }

    public function pinned(
        User $viewer,
        string $scope = self::PIN_SCOPE_COMPANY,
        int $perPage = 5,
        int $page = 1,
        ?int $departmentId = null,
        ?int $wallUserId = null,
        ?string $search = null,
    ): array {
        $scope = $this->normalizePinScope($scope);
        $paginator = $this->posts->paginatePinned($perPage, $page, $scope, $departmentId, $wallUserId, $search, $viewer->id, $viewer->department_id);

        return [
            'posts' => collect($paginator->items())
                ->map(fn (SocialPost $post) => $this->present($post, $viewer))
                ->values(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }

    public function wallProfile(int $userId, User $viewer): ?array
    {
        $user = $this->users->findById($userId);
        if ($user === null) {
            return null;
        }

        $stats = $this->posts->profileStats($user->id);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar_url,
                'department' => $user->department?->name,
                'email' => (int) $user->id === (int) $viewer->id ? $user->email : null,
            ],
            'stats' => $stats,
            'is_own' => (int) $user->id === (int) $viewer->id,
        ];
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

    private function isActingSuperAdmin(User $viewer): bool
    {
        return ! $this->viewAs->isImpersonating() && $viewer->isSuperAdmin();
    }

    private function normalizePinScope(string $scope): string
    {
        return $scope === self::PIN_SCOPE_SYSTEM
            ? self::PIN_SCOPE_SYSTEM
            : self::PIN_SCOPE_COMPANY;
    }

    public function create(User $author, array $data, array $files = []): SocialPost
    {
        $asSystem = (bool) ($data['as_system_announcement'] ?? false);
        $destination = $this->resolveDestination($author, $data);

        if ($asSystem && ! $this->isActingSuperAdmin($author)) {
            throw ValidationException::withMessages([
                'as_system_announcement' => ['Chỉ người quản trị mới được đăng thông báo quan trọng.'],
            ]);
        }

        if ($asSystem && $destination['post_scope'] !== self::POST_SCOPE_COMPANY) {
            throw ValidationException::withMessages([
                'post_scope' => ['Thông báo quan trọng luôn đăng trên bảng tin chung.'],
            ]);
        }

        $visibility = $this->resolveDepartmentVisibility($data, $destination['post_scope']);

        $post = DB::transaction(function () use ($author, $data, $files, $asSystem, $destination, $visibility) {
            $payload = [
                'user_id' => $author->id,
                'department_id' => $destination['department_id'],
                'wall_user_id' => $destination['wall_user_id'],
                'group_id' => $destination['group_id'],
                'department_visibility_mode' => $visibility['mode'],
                'content' => isset($data['content']) ? $this->sanitizeContent($data['content']) : null,
            ];

            if ($asSystem) {
                $payload['is_pinned'] = true;
                $payload['pin_scope'] = self::PIN_SCOPE_SYSTEM;
                $payload['pinned_by'] = $author->id;
                $payload['pinned_at'] = now();
            }

            $post = $this->posts->create($payload);

            if ($visibility['department_ids'] !== []) {
                $this->posts->syncDepartmentVisibility($post, $visibility['department_ids']);
                $post = $this->posts->find($post->id) ?? $post;
            }

            if ($files !== []) {
                $post = $this->posts->update($post, [
                    'attachments' => $this->storeAttachments($post->id, $files),
                ]);
            }

            if (isset($data['poll']) && is_array($data['poll'])) {
                $this->polls->createForPost($post, $data['poll']);
                $post = $this->posts->find($post->id) ?? $post;
            }

            return $post;
        });

        $this->mentions->notifyPost($author, $post);
        $this->hashtags->syncForPost($post);

        return $post;
    }

    public function update(SocialPost $post, User $editor, array $data): SocialPost
    {
        if ((int) $post->user_id !== (int) $editor->id) {
            throw ValidationException::withMessages([
                'content' => ['Chỉ tác giả mới được sửa bài viết này.'],
            ]);
        }

        $newContent = $this->sanitizeContent((string) ($data['content'] ?? ''));
        $oldContent = (string) ($post->content ?? '');

        if ($newContent === '' && $post->poll === null && ($post->attachments ?? []) === []) {
            throw ValidationException::withMessages([
                'content' => ['Nội dung bài viết không được để trống.'],
            ]);
        }

        if ($newContent === $oldContent) {
            return $post;
        }

        $this->posts->addRevision($post, [
            'user_id' => $editor->id,
            'content' => $oldContent === '' ? null : $oldContent,
            'published_at' => $post->content_updated_at ?? $post->created_at ?? now(),
        ]);

        $post = $this->posts->update($post, [
            'content' => $newContent,
            'content_updated_at' => now(),
        ]);

        $this->mentions->notifyPost($editor, $post, $oldContent);
        $this->hashtags->syncForPost($post);

        return $post;
    }

    public function revisionHistory(SocialPost $post): array
    {
        $currentPublishedAt = $post->content_updated_at ?? $post->created_at;

        $versions = [
            [
                'id' => null,
                'content' => $post->content,
                'published_at' => $currentPublishedAt?->toIso8601String(),
                'is_current' => true,
            ],
        ];

        foreach ($this->posts->revisions($post) as $revision) {
            $versions[] = [
                'id' => $revision->id,
                'content' => $revision->content,
                'published_at' => $revision->published_at?->toIso8601String(),
                'is_current' => false,
            ];
        }

        return ['versions' => $versions];
    }

    public function delete(SocialPost $post): void
    {
        foreach ($post->attachments ?? [] as $attachment) {
            if (isset($attachment['path'])) {
                Storage::disk('public')->delete($attachment['path']);
            }
        }

        $this->polls->deleteImage($post->poll);
        $this->hashtags->detachForPost($post);
        $this->posts->delete($post);
    }

    public function share(User $sharer, SocialPost $original, ?string $caption, array $data = []): SocialPost
    {
        $destination = $this->resolveDestination($sharer, $data);

        $post = $this->posts->create([
            'user_id' => $sharer->id,
            'department_id' => $destination['department_id'],
            'wall_user_id' => $destination['wall_user_id'],
            'group_id' => $destination['group_id'],
            'content' => $caption !== null ? $this->sanitizeContent($caption) : null,
            'shared_from_post_id' => $original->id,
        ]);

        $this->mentions->notifyPost($sharer, $post);
        $this->hashtags->syncForPost($post);

        return $post;
    }

    /**
     * @return array{post_scope: string, department_id: int|null, wall_user_id: int|null, group_id: int|null}
     */
    private function resolveDestination(User $actor, array $data): array
    {
        $postScope = $data['post_scope'] ?? self::POST_SCOPE_COMPANY;

        if ($postScope === self::POST_SCOPE_GROUP) {
            $groupId = isset($data['group_id']) ? (int) $data['group_id'] : null;
            if ($groupId === null) {
                throw ValidationException::withMessages([
                    'group_id' => ['Thiếu nhóm để đăng bài.'],
                ]);
            }

            if ($this->groups->membership($groupId, $actor->id) === null) {
                throw ValidationException::withMessages([
                    'group_id' => ['Bạn chưa là thành viên của nhóm này.'],
                ]);
            }

            return [
                'post_scope' => self::POST_SCOPE_GROUP,
                'department_id' => null,
                'wall_user_id' => null,
                'group_id' => $groupId,
            ];
        }

        if ($postScope === self::POST_SCOPE_PERSONAL) {
            $wallUserId = isset($data['wall_user_id']) ? (int) $data['wall_user_id'] : $actor->id;
            $wallUser = $this->users->findById($wallUserId);

            if ($wallUser === null || ! $wallUser->isActive()) {
                throw ValidationException::withMessages([
                    'wall_user_id' => ['Không tìm thấy tường cá nhân này.'],
                ]);
            }

            return [
                'post_scope' => self::POST_SCOPE_PERSONAL,
                'department_id' => null,
                'wall_user_id' => $wallUser->id,
                'group_id' => null,
            ];
        }

        if ($postScope === self::POST_SCOPE_DEPARTMENT) {
            if ($actor->department_id === null) {
                throw ValidationException::withMessages([
                    'post_scope' => ['Bạn chưa thuộc phòng ban nào nên không thể đăng lên tường phòng ban.'],
                ]);
            }

            return [
                'post_scope' => self::POST_SCOPE_DEPARTMENT,
                'department_id' => $actor->department_id,
                'wall_user_id' => null,
                'group_id' => null,
            ];
        }

        return [
            'post_scope' => self::POST_SCOPE_COMPANY,
            'department_id' => null,
            'wall_user_id' => null,
            'group_id' => null,
        ];
    }

    /**
     * Diễn giải lựa chọn phòng ban được thấy/loại trừ. Chỉ áp dụng khi đăng lên
     * bảng tin chung; các tường khác (phòng ban, cá nhân, nhóm) luôn 'all'.
     *
     * @return array{mode: string, department_ids: int[]}
     */
    private function resolveDepartmentVisibility(array $data, string $postScope): array
    {
        if ($postScope !== self::POST_SCOPE_COMPANY) {
            return ['mode' => self::DEPT_VISIBILITY_ALL, 'department_ids' => []];
        }

        $mode = $data['department_visibility_mode'] ?? self::DEPT_VISIBILITY_ALL;
        if (! in_array($mode, [self::DEPT_VISIBILITY_INCLUDE, self::DEPT_VISIBILITY_EXCLUDE], true)) {
            return ['mode' => self::DEPT_VISIBILITY_ALL, 'department_ids' => []];
        }

        $departmentIds = collect($data['department_visibility_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($departmentIds === []) {
            throw ValidationException::withMessages([
                'department_visibility_ids' => ['Chọn ít nhất 1 phòng ban.'],
            ]);
        }

        return ['mode' => $mode, 'department_ids' => $departmentIds];
    }

    private function presentPostScope(SocialPost $post): string
    {
        if ($post->group_id !== null) {
            return self::POST_SCOPE_GROUP;
        }

        if ($post->wall_user_id !== null) {
            return self::POST_SCOPE_PERSONAL;
        }

        return $post->department_id === null ? self::POST_SCOPE_COMPANY : self::POST_SCOPE_DEPARTMENT;
    }

    private function isGroupManager(?int $groupId, User $viewer): bool
    {
        if ($groupId === null) {
            return false;
        }

        $membership = $this->groups->membership($groupId, $viewer->id);

        return $membership !== null && in_array($membership->role, [SocialGroupMember::ROLE_OWNER, SocialGroupMember::ROLE_ADMIN], true);
    }

    private function presentUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatar_url,
            'department' => $user->department?->name,
        ];
    }

    public function setReaction(SocialPost $post, User $user, string $type): array
    {
        $result = $this->posts->setReaction($post, $user->id, $type);

        return [
            'reactions' => $this->posts->reactionSummary($post),
            'my_reaction' => $result['reaction_type'],
        ];
    }

    public function recordView(SocialPost $post, User $viewer): array
    {
        $recorded = $this->posts->recordView($post, $viewer->id);

        return [
            'views_count' => $this->posts->viewsCount($post),
            'viewed' => (int) $post->user_id !== (int) $viewer->id,
            'recorded' => $recorded,
        ];
    }

    public function reactionUsers(SocialPost $post, ?string $type = null): array
    {
        return [
            'users' => $this->posts->reactionUsers($post, $type)
                ->map(fn (SocialPostLike $like) => $this->presentReactionUser($like->user, $like->reaction_type))
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

    public function pin(SocialPost $post, User $actor, string $scope = self::PIN_SCOPE_COMPANY): SocialPost
    {
        $scope = $this->normalizePinScope($scope);

        if ($scope === self::PIN_SCOPE_SYSTEM && ! $this->isActingSuperAdmin($actor)) {
            throw ValidationException::withMessages([
                'scope' => ['Chỉ người quản trị mới được đưa bài viết lên thông báo quan trọng.'],
            ]);
        }

        return $this->posts->update($post, [
            'is_pinned' => true,
            'pin_scope' => $scope,
            'pinned_by' => $actor->id,
            'pinned_at' => now(),
        ]);
    }

    public function unpin(SocialPost $post): SocialPost
    {
        return $this->posts->update($post, [
            'is_pinned' => false,
            'pin_scope' => null,
            'pinned_by' => null,
            'pinned_at' => null,
        ]);
    }

    public function present(SocialPost $post, User $viewer): array
    {
        return [
            'id' => $post->id,
            'content' => $post->content,
            'hashtags' => $post->hashtags
                ->map(fn (SocialHashtag $hashtag) => [
                    'name' => $hashtag->name,
                    'label' => $hashtag->label,
                ])
                ->values()
                ->all(),
            'attachments' => collect($post->attachments ?? [])->map(fn (array $a) => [
                'type' => $a['type'],
                'name' => $a['name'],
                'size' => $a['size'],
                'url' => Storage::disk('public')->url($a['path']),
            ])->all(),
            'author' => $this->presentUser($post->user),
            'post_scope' => $this->presentPostScope($post),
            'department' => $post->department?->name,
            'department_visibility_mode' => $post->department_visibility_mode ?? self::DEPT_VISIBILITY_ALL,
            'department_visibility' => $this->presentDepartmentVisibility($post),
            'wall_user' => $post->wall_user_id !== null ? $this->presentUser($post->wallUser) : null,
            'group' => $post->group_id !== null ? [
                'id' => $post->group->id,
                'name' => $post->group->name,
                'avatar_url' => $post->group->avatar_path
                    ? Storage::disk('public')->url($post->group->avatar_path)
                    : null,
            ] : null,
            'is_pinned' => $post->is_pinned,
            'pin_scope' => $post->is_pinned ? ($post->pin_scope ?: self::PIN_SCOPE_COMPANY) : null,
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
            'views_count' => (int) ($post->views_count ?? 0),
            'viewed' => (int) ($post->getAttributes()['viewed'] ?? 0) === 1,
            'can_edit' => (int) $post->user_id === (int) $viewer->id,
            'can_delete' => (int) $post->user_id === (int) $viewer->id
                || $this->canModerate($viewer, $post->user->department_id)
                || $this->isGroupManager($post->group_id, $viewer),
            'can_pin' => $this->canPin($viewer) && $post->wall_user_id === null && $post->group_id === null,
            'is_edited' => $post->content_updated_at !== null,
            'edited_at' => $post->content_updated_at?->toIso8601String(),
            'created_at' => $post->created_at?->toIso8601String(),
            'poll' => $this->polls->present(
                $post->poll,
                $viewer,
                (int) $post->user_id === (int) $viewer->id,
            ),
        ];
    }

    /** @return array{id: int, name: string}[]|null null nếu bài không giới hạn phòng ban ('all'). */
    private function presentDepartmentVisibility(SocialPost $post): ?array
    {
        if (($post->department_visibility_mode ?? self::DEPT_VISIBILITY_ALL) === self::DEPT_VISIBILITY_ALL) {
            return null;
        }

        return $post->departmentVisibilities
            ->map(fn (SocialPostDepartmentVisibility $v) => [
                'id' => $v->department->id,
                'name' => $v->department->name,
            ])
            ->values()
            ->all();
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
