<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\Task;
use Modules\Social\App\Models\SocialPost;
use Modules\Social\App\Services\SocialContentSanitizer;

/**
 * Meta Open Graph theo URL SPA — bot Zalo/FB/iMessage đọc được trước khi Vue boot.
 */
final class SpaOpenGraphResolver
{
    private const DEFAULT_TITLE = 'VA Workspace';

    private const DEFAULT_DESCRIPTION = 'Phần mềm làm việc nội bộ VA Schools: công việc, chấm công, đơn từ, bảng tin và trò chuyện trên một nền tảng.';

    private const INTRO_DESCRIPTION = 'Mời đồng nghiệp truy cập VA Workspace bằng tài khoản mail do nhà trường cung cấp. Cài PWA trên điện thoại để nhận thông báo và làm việc mọi lúc.';

    /** @var array<string, array{title: string, description: string}> */
    private const STATIC_ROUTES = [
        '/' => [
            'title' => 'VA Workspace · Phần mềm quản lý nội bộ',
            'description' => self::DEFAULT_DESCRIPTION,
        ],
        '/share' => [
            'title' => 'VA Workspace · Giới thiệu phần mềm',
            'description' => self::INTRO_DESCRIPTION,
        ],
        '/login' => [
            'title' => 'Đăng nhập · VA Workspace',
            'description' => 'Đăng nhập VA Workspace bằng tài khoản Google mail do nhà trường cung cấp.',
        ],
        '/social' => [
            'title' => 'Bảng tin · VA Workspace',
            'description' => 'Cập nhật, thảo luận và kết nối với đồng nghiệp trong workspace.',
        ],
        '/social/groups' => [
            'title' => 'Nhóm · VA Workspace',
            'description' => 'Tham gia nhóm và trao đổi trong workspace VA Schools.',
        ],
        '/dashboard/me' => [
            'title' => 'Trang của tôi · VA Workspace',
            'description' => 'Ứng dụng nhân viên VA Workspace trên điện thoại.',
        ],
        '/dashboard/me/feed' => [
            'title' => 'Bảng tin · VA Workspace',
            'description' => 'Thông báo và bài viết mới dành cho bạn.',
        ],
        '/dashboard/me/chat' => [
            'title' => 'Tin nhắn · VA Workspace',
            'description' => 'Trò chuyện nội bộ với đồng nghiệp.',
        ],
        '/dashboard/me/attendance' => [
            'title' => 'Chấm công · VA Workspace',
            'description' => 'Chấm công và theo dõi ca làm việc.',
        ],
        '/dashboard/me/leave' => [
            'title' => 'Nghỉ phép · VA Workspace',
            'description' => 'Đăng ký và theo dõi nghỉ phép.',
        ],
        '/dashboard/me/requests' => [
            'title' => 'Đơn từ · VA Workspace',
            'description' => 'Gửi và theo dõi đơn từ nội bộ.',
        ],
        '/dashboard/me/profile' => [
            'title' => 'Hồ sơ · VA Workspace',
            'description' => 'Thông tin cá nhân trên VA Workspace.',
        ],
        '/dashboard/me/utilities' => [
            'title' => 'Tiện ích · VA Workspace',
            'description' => 'Lối tắt tiện ích nội bộ VA Schools.',
        ],
        '/dashboard/me/contacts' => [
            'title' => 'Danh bạ · VA Workspace',
            'description' => 'Tìm và liên hệ đồng nghiệp.',
        ],
        '/manager/project' => [
            'title' => 'Dự án · VA Workspace',
            'description' => 'Quản lý dự án và công việc nội bộ.',
        ],
        '/manager/project/tasks' => [
            'title' => 'Công việc · VA Workspace',
            'description' => 'Danh sách công việc trên workspace.',
        ],
    ];

    public function __construct(
        private readonly SocialContentSanitizer $sanitizer,
    ) {}

    /**
     * @return array{title: string, description: string, image: ?string, url: string}
     */
    public function resolve(Request $request): array
    {
        $path = $this->normalizePath($request->getPathInfo());
        $pageUrl = rtrim((string) config('app.url'), '/').($path === '/' ? '/' : $path);
        $query = $request->query();
        if ($query !== []) {
            $pageUrl .= '?'.http_build_query($query);
        }

        $cover = $this->defaultCoverUrl();

        if ($postId = $this->intQuery($query, 'post')) {
            return $this->resolveSocialPost($postId, $pageUrl, $cover) ?? $this->staticOrDefault($path, $pageUrl, $cover);
        }

        if ($wallId = $this->intQuery($query, 'wall')) {
            return $this->resolveWall($wallId, $pageUrl, $cover) ?? $this->staticOrDefault($path, $pageUrl, $cover);
        }

        if (preg_match('#^/manager/project/tasks/(\d+)$#', $path, $m)) {
            return $this->resolveTask((int) $m[1], $pageUrl, $cover) ?? $this->staticOrDefault($path, $pageUrl, $cover);
        }

        if (preg_match('#^/manager/project/(\d+)$#', $path, $m)) {
            return $this->resolveProject((int) $m[1], $pageUrl, $cover) ?? $this->staticOrDefault($path, $pageUrl, $cover);
        }

        if (preg_match('#^/social/groups/(\d+)$#', $path, $m)) {
            return [
                'title' => 'Nhóm · VA Workspace',
                'description' => 'Xem thảo luận nhóm trên workspace (cần đăng nhập).',
                'image' => $cover,
                'url' => $pageUrl,
            ];
        }

        return $this->staticOrDefault($path, $pageUrl, $cover);
    }

    /**
     * @return array{title: string, description: string, image: ?string, url: string}
     */
    private function staticOrDefault(string $path, string $pageUrl, string $cover): array
    {
        $static = self::STATIC_ROUTES[$path] ?? null;
        if ($static !== null) {
            return [
                'title' => $static['title'],
                'description' => $static['description'],
                'image' => $cover,
                'url' => $pageUrl,
            ];
        }

        return [
            'title' => self::DEFAULT_TITLE,
            'description' => self::DEFAULT_DESCRIPTION,
            'image' => $cover,
            'url' => $pageUrl,
        ];
    }

    /**
     * @return array{title: string, description: string, image: ?string, url: string}|null
     */
    private function resolveSocialPost(int $postId, string $pageUrl, string $cover): ?array
    {
        $post = SocialPost::query()
            ->with('user:id,name')
            ->where('id', $postId)
            ->where('review_status', SocialPost::REVIEW_APPROVED)
            ->first();

        if ($post === null) {
            return null;
        }

        $author = $post->is_anonymous
            ? (trim((string) ($post->anonymous_name ?? '')) ?: 'Ẩn danh')
            : trim((string) ($post->user?->name ?? 'Thành viên'));

        $excerpt = $this->sanitizer->excerpt((string) ($post->content ?? ''), 160);
        if ($excerpt === '') {
            $excerpt = 'Bài viết trên bảng tin VA Workspace.';
        }

        return [
            'title' => "{$author} · Bảng tin · VA Workspace",
            'description' => $excerpt,
            'image' => $this->socialPostImage($post) ?? $cover,
            'url' => $pageUrl,
        ];
    }

    /**
     * @return array{title: string, description: string, image: ?string, url: string}|null
     */
    private function resolveWall(int $userId, string $pageUrl, string $cover): ?array
    {
        $user = User::query()->where('id', $userId)->where('status', 'active')->first(['id', 'name']);
        if ($user === null) {
            return null;
        }

        $name = trim((string) $user->name) ?: 'Thành viên';

        return [
            'title' => "Tường {$name} · VA Workspace",
            'description' => "Xem bài viết và cập nhật trên tường cá nhân của {$name}.",
            'image' => $cover,
            'url' => $pageUrl,
        ];
    }

    /**
     * @return array{title: string, description: string, image: ?string, url: string}|null
     */
    private function resolveTask(int $taskId, string $pageUrl, string $cover): ?array
    {
        $task = Task::query()->find($taskId, ['id', 'title', 'code']);
        if ($task === null) {
            return null;
        }

        $label = trim((string) $task->title) ?: 'Công việc';
        $code = trim((string) ($task->code ?? ''));

        return [
            'title' => ($code !== '' ? "{$code} · " : '')."{$label} · VA Workspace",
            'description' => 'Chi tiết công việc trên workspace VA Schools.',
            'image' => $cover,
            'url' => $pageUrl,
        ];
    }

    /**
     * @return array{title: string, description: string, image: ?string, url: string}|null
     */
    private function resolveProject(int $projectId, string $pageUrl, string $cover): ?array
    {
        $project = Project::query()->find($projectId, ['id', 'name', 'code']);
        if ($project === null) {
            return null;
        }

        $name = trim((string) $project->name) ?: 'Dự án';
        $code = trim((string) ($project->code ?? ''));

        return [
            'title' => ($code !== '' ? "{$code} · " : '')."{$name} · VA Workspace",
            'description' => 'Thông tin dự án trên workspace VA Schools.',
            'image' => $cover,
            'url' => $pageUrl,
        ];
    }

    private function socialPostImage(SocialPost $post): ?string
    {
        foreach ($post->attachments ?? [] as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }
            if (($attachment['type'] ?? '') !== 'image') {
                continue;
            }
            $url = $attachment['url'] ?? null;
            if (is_string($url) && $url !== '') {
                return $this->absoluteUrl($url);
            }
            $path = $attachment['path'] ?? null;
            if (is_string($path) && $path !== '') {
                return Storage::disk('s3')->url($path);
            }
        }

        return null;
    }

    private function absoluteUrl(string $url): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }

    private function defaultCoverUrl(): string
    {
        $version = (string) config('app.og_cover_version', '1');

        return asset('images/og-cover.jpg').'?v='.rawurlencode($version);
    }

    private function normalizePath(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        return rtrim($path, '/') ?: '/';
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function intQuery(array $query, string $key): ?int
    {
        $raw = $query[$key] ?? null;
        if (! is_scalar($raw)) {
            return null;
        }
        $id = (int) $raw;

        return $id > 0 ? $id : null;
    }
}
