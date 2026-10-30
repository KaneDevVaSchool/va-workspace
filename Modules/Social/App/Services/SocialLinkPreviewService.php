<?php

namespace Modules\Social\App\Services;

use App\Services\ExternalOpenGraphFetcher;
use App\Services\SpaOpenGraphResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Social\App\Models\SocialLinkPreview;
use Modules\Social\App\Models\SocialPost;
use Throwable;

class SocialLinkPreviewService
{
    private const MAX_LINKS_PER_POST = 5;

    private const HTTP_TIMEOUT_SECONDS = 3;

    public function __construct(
        private readonly SpaOpenGraphResolver $openGraph,
        private readonly ExternalOpenGraphFetcher $externalOpenGraph,
    ) {}

    /**
     * @return list<array{url: string, provider: string}>
     */
    public function extractUrlsFromHtml(string $html): array
    {
        if ($html === '') {
            return [];
        }

        $plain = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        if ($plain === '' || ! preg_match_all('/https?:\/\/[^\s<>"]+/i', $plain, $matches)) {
            return [];
        }

        $found = [];
        $seen = [];
        foreach ($matches[0] as $raw) {
            $url = rtrim($raw, ".,;:!?)'\"");
            $provider = $this->detectProvider($url);
            if ($provider === null || isset($seen[$url])) {
                continue;
            }

            $seen[$url] = true;
            $found[] = ['url' => $url, 'provider' => $provider];
            if (count($found) >= self::MAX_LINKS_PER_POST) {
                break;
            }
        }

        return $found;
    }

    private function normalizePreviewUrl(string $url): string
    {
        return rtrim(trim($url), ".,;:!?)'\"");
    }

    private function detectProvider(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host)) {
            return null;
        }
        $host = strtolower($host);

        if ($this->isYoutubeHost($host)) {
            return SocialLinkPreview::PROVIDER_YOUTUBE;
        }

        if ($host === 'fb.watch' || str_ends_with($host, '.facebook.com') || $host === 'facebook.com') {
            return SocialLinkPreview::PROVIDER_FACEBOOK;
        }

        if (str_ends_with($host, '.tiktok.com') || $host === 'tiktok.com') {
            return SocialLinkPreview::PROVIDER_TIKTOK;
        }

        if ($this->isWorkspaceHost($host)) {
            return SocialLinkPreview::PROVIDER_WORKSPACE;
        }

        if (preg_match('#^https?://#i', $url)) {
            return SocialLinkPreview::PROVIDER_WEBSITE;
        }

        return null;
    }

    /**
     * @return array{provider: string, external_id: ?string, title: ?string, description: ?string, site_name: ?string, thumbnail_url: ?string, embed_url: ?string, is_live: bool}|null
     */
    public function resolveOne(string $url): ?array
    {
        $provider = $this->detectProvider($url);

        try {
            return match ($provider) {
                SocialLinkPreview::PROVIDER_YOUTUBE => $this->resolveYoutube($url),
                SocialLinkPreview::PROVIDER_FACEBOOK => $this->resolveFacebook($url),
                SocialLinkPreview::PROVIDER_TIKTOK => $this->resolveTiktok($url),
                SocialLinkPreview::PROVIDER_WORKSPACE => $this->resolveWorkspace($url),
                SocialLinkPreview::PROVIDER_WEBSITE => $this->resolveWebsite($url),
                default => null,
            };
        } catch (Throwable $e) {
            Log::warning('social.link_preview.resolve_failed', [
                'url' => $url,
                'provider' => $provider,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function previewForUrl(string $url): ?array
    {
        return $this->resolveOne($url);
    }

    /**
     * @param  list<string>  $dismissedUrls
     */
    public function syncForPost(SocialPost $post, array $dismissedUrls = []): void
    {
        $content = (string) ($post->content ?? '');
        $dismissed = collect($dismissedUrls)
            ->map(fn ($url) => $this->normalizePreviewUrl((string) $url))
            ->filter()
            ->flip();
        $links = collect($this->extractUrlsFromHtml($content))
            ->reject(fn (array $link) => $dismissed->has($this->normalizePreviewUrl($link['url'])))
            ->values()
            ->all();

        $post->linkPreviews()->delete();

        if ($links === []) {
            return;
        }

        $position = 0;
        foreach ($links as $link) {
            $resolved = $this->resolveOne($link['url']);
            if ($resolved === null) {
                continue;
            }

            SocialLinkPreview::query()->create([
                'post_id' => $post->id,
                'url' => $link['url'],
                'provider' => $resolved['provider'],
                'external_id' => $resolved['external_id'],
                'title' => $resolved['title'],
                'description' => $resolved['description'] ?? null,
                'site_name' => $resolved['site_name'] ?? null,
                'thumbnail_url' => $resolved['thumbnail_url'],
                'embed_url' => $resolved['embed_url'],
                'is_live' => $resolved['is_live'],
                'position' => $position++,
            ]);
        }

        $post->unsetRelation('linkPreviews');
    }

    private function isYoutubeHost(string $host): bool
    {
        $host = strtolower($host);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host === 'youtu.be'
            || $host === 'youtube.com'
            || $host === 'youtube-nocookie.com'
            || str_ends_with($host, '.youtube.com')
            || str_ends_with($host, '.youtube-nocookie.com');
    }

    private function extractYoutubeVideoId(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || ! $this->isYoutubeHost($host)) {
            return null;
        }

        $bare = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
        $path = (string) parse_url($url, PHP_URL_PATH);

        if ($bare === 'youtu.be') {
            $segment = explode('/', trim($path, '/'))[0] ?? '';

            return $this->normalizeYoutubeId(is_string($segment) ? $segment : '');
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        if (isset($query['v']) && is_string($query['v'])) {
            $fromQuery = $this->normalizeYoutubeId($query['v']);
            if ($fromQuery !== null) {
                return $fromQuery;
            }
        }

        if (preg_match('#/(?:live|shorts|embed|v)/([A-Za-z0-9_-]{11})(?:/|$)#', $path, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function normalizeYoutubeId(string $candidate): ?string
    {
        return preg_match('/^[A-Za-z0-9_-]{11}$/', $candidate) === 1 ? $candidate : null;
    }

    private function youtubeTimeParam(string $url): ?string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        foreach (['t', 'start'] as $key) {
            if (isset($query[$key]) && is_string($query[$key]) && $query[$key] !== '') {
                return $query[$key];
            }
        }

        $fragment = (string) parse_url($url, PHP_URL_FRAGMENT);
        if ($fragment !== '' && preg_match('/(?:^|&)t=([^&]+)/', $fragment, $matches) === 1) {
            return urldecode($matches[1]);
        }

        return null;
    }

    private function youtubeStartSeconds(string $url): ?int
    {
        $raw = $this->youtubeTimeParam($url);
        if ($raw === null) {
            return null;
        }

        $raw = strtolower(trim($raw));
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $raw) === 1) {
            $seconds = (int) $raw;

            return $seconds > 0 ? $seconds : null;
        }

        if (preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', $raw, $matches) !== 1) {
            return null;
        }

        $seconds = ((int) ($matches[1] ?? 0) * 3600)
            + ((int) ($matches[2] ?? 0) * 60)
            + (int) ($matches[3] ?? 0);

        return $seconds > 0 ? $seconds : null;
    }

    /**
     * Embed lưu trong DB không gắn `origin`: origin là của trình duyệt
     * (local khác production). Card tự ghép origin lúc phát để tránh Error 153.
     */
    private function youtubeEmbedUrl(string $videoId, string $sourceUrl): string
    {
        $params = [
            'playsinline' => '1',
            'rel' => '0',
            'hl' => 'vi',
        ];
        $start = $this->youtubeStartSeconds($sourceUrl);
        if ($start !== null) {
            $params['start'] = $start;
        }

        return 'https://www.youtube.com/embed/'.$videoId.'?'.http_build_query($params);
    }

    private function youtubeThumbnailUrl(array $thumbnails, string $videoId): string
    {
        foreach (['maxres', 'standard', 'high', 'medium', 'default'] as $key) {
            $url = $thumbnails[$key]['url'] ?? null;
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg";
    }

    private function clipText(mixed $value, int $limit): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim((string) (preg_replace('/\s+/u', ' ', $value) ?? ''));
        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit - 1)).'…';
    }

    private function resolveYoutube(string $url): ?array
    {
        $videoId = $this->extractYoutubeVideoId($url);
        if ($videoId === null) {
            return null;
        }

        $apiKey = config('services.youtube.key');
        if (is_string($apiKey) && $apiKey !== '') {
            $response = Http::timeout(self::HTTP_TIMEOUT_SECONDS)->get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'snippet,liveStreamingDetails',
                'id' => $videoId,
                'key' => $apiKey,
            ]);

            $item = $response->ok() ? ($response->json('items.0')) : null;
            if (is_array($item)) {
                $snippet = is_array($item['snippet'] ?? null) ? $item['snippet'] : [];
                $thumbnails = is_array($snippet['thumbnails'] ?? null) ? $snippet['thumbnails'] : [];

                return [
                    'provider' => SocialLinkPreview::PROVIDER_YOUTUBE,
                    'external_id' => $videoId,
                    'title' => $this->clipText($snippet['title'] ?? null, 500),
                    'description' => $this->clipText($snippet['description'] ?? null, 1000),
                    'site_name' => $this->clipText($snippet['channelTitle'] ?? null, 191),
                    'thumbnail_url' => $this->youtubeThumbnailUrl($thumbnails, $videoId),
                    'embed_url' => $this->youtubeEmbedUrl($videoId, $url),
                    'is_live' => ($snippet['liveBroadcastContent'] ?? 'none') === 'live',
                ];
            }
        }

        $oembed = Http::timeout(self::HTTP_TIMEOUT_SECONDS)
            ->get('https://www.youtube.com/oembed', ['url' => $url, 'format' => 'json']);

        if (! $oembed->ok()) {
            return null;
        }

        return [
            'provider' => SocialLinkPreview::PROVIDER_YOUTUBE,
            'external_id' => $videoId,
            'title' => $this->clipText($oembed->json('title'), 500),
            'description' => null,
            'site_name' => $this->clipText($oembed->json('author_name'), 191),
            'thumbnail_url' => "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg",
            'embed_url' => $this->youtubeEmbedUrl($videoId, $url),
            'is_live' => false,
        ];
    }

    private function resolveFacebook(string $url): ?array
    {
        $response = Http::timeout(self::HTTP_TIMEOUT_SECONDS)
            ->get('https://www.facebook.com/plugins/video/oembed.json/', ['url' => $url]);

        if (! $response->ok()) {
            return [
                'provider' => SocialLinkPreview::PROVIDER_FACEBOOK,
                'external_id' => null,
                'title' => null,
                'thumbnail_url' => null,
                'embed_url' => null,
                'is_live' => false,
            ];
        }

        // Facebook oEmbed không trả embed_url riêng, chỉ trả snippet <iframe>
        // sẵn trong `html`. Dựng lại embed_url từ chính Facebook Video Plugin
        // (iframe src chuẩn) để component preview render <iframe> nhất quán
        // với YouTube/TikTok, thay vì phải v-html snippet trả về (rủi ro XSS).
        $hasEmbeddableHtml = is_string($response->json('html')) && $response->json('html') !== '';

        return [
            'provider' => SocialLinkPreview::PROVIDER_FACEBOOK,
            'external_id' => null,
            'title' => $response->json('title'),
            'thumbnail_url' => $response->json('thumbnail_url'),
            'embed_url' => $hasEmbeddableHtml
                ? 'https://www.facebook.com/plugins/video.php?href='.urlencode($url)
                : null,
            'is_live' => false,
        ];
    }

    private function resolveTiktok(string $url): array
    {
        $isLiveUrl = (bool) preg_match('#/live(?:/|$|\?)#i', $url);

        if ($isLiveUrl) {
            return [
                'provider' => SocialLinkPreview::PROVIDER_TIKTOK,
                'external_id' => null,
                'title' => null,
                'thumbnail_url' => null,
                'embed_url' => null,
                'is_live' => true,
            ];
        }

        $response = Http::timeout(self::HTTP_TIMEOUT_SECONDS)
            ->get('https://www.tiktok.com/oembed', ['url' => $url]);

        if (! $response->ok()) {
            return [
                'provider' => SocialLinkPreview::PROVIDER_TIKTOK,
                'external_id' => null,
                'title' => null,
                'thumbnail_url' => null,
                'embed_url' => null,
                'is_live' => false,
            ];
        }

        $videoId = $response->json('embed_product_id');

        return [
            'provider' => SocialLinkPreview::PROVIDER_TIKTOK,
            'external_id' => is_string($videoId) ? $videoId : null,
            'title' => $response->json('title'),
            'thumbnail_url' => $response->json('thumbnail_url'),
            'embed_url' => is_string($videoId) && $videoId !== ''
                ? "https://www.tiktok.com/embed/v2/{$videoId}"
                : null,
            'is_live' => false,
        ];
    }

    private function isWorkspaceHost(string $host): bool
    {
        $host = strtolower($host);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (is_string($appHost) && strtolower($appHost) === $host) {
            return true;
        }

        $extra = config('app.workspace_preview_hosts', []);
        if (! is_array($extra)) {
            return false;
        }

        foreach ($extra as $allowed) {
            if (is_string($allowed) && strtolower($allowed) === $host) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{provider: string, external_id: null, title: string, description: string, site_name: string, thumbnail_url: ?string, embed_url: null, is_live: false}|null
     */
    /**
     * @return array{provider: string, external_id: null, title: string, description: string, site_name: string, thumbnail_url: ?string, embed_url: null, is_live: false}|null
     */
    private function resolveWebsite(string $url): ?array
    {
        $normalized = $this->normalizePreviewUrl($url);
        $meta = $this->externalOpenGraph->fetch($normalized);
        if ($meta === null) {
            return null;
        }

        $host = parse_url($normalized, PHP_URL_HOST);

        return [
            'provider' => SocialLinkPreview::PROVIDER_WEBSITE,
            'external_id' => null,
            'title' => $meta['title'],
            'description' => $meta['description'],
            'site_name' => $meta['site_name'] !== '' ? $meta['site_name'] : (is_string($host) ? $host : ''),
            'thumbnail_url' => $meta['image'],
            'embed_url' => null,
            'is_live' => false,
        ];
    }

    private function resolveWorkspace(string $url): ?array
    {
        $normalized = $this->normalizePreviewUrl($url);
        $parts = parse_url($normalized);
        if (! is_array($parts)) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }

        $query = [];
        if (! empty($parts['query']) && is_string($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $meta = $this->openGraph->resolve(Request::create($path, 'GET', $query));

        return [
            'provider' => SocialLinkPreview::PROVIDER_WORKSPACE,
            'external_id' => null,
            'title' => (string) ($meta['title'] ?? 'VA Workspace'),
            'description' => (string) ($meta['description'] ?? ''),
            'site_name' => 'VA Workspace',
            'thumbnail_url' => isset($meta['image']) && is_string($meta['image']) ? $meta['image'] : null,
            'embed_url' => null,
            'is_live' => false,
        ];
    }
}
