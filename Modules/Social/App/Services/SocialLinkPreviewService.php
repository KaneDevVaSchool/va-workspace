<?php

namespace Modules\Social\App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Social\App\Models\SocialLinkPreview;
use Modules\Social\App\Models\SocialPost;
use Throwable;

class SocialLinkPreviewService
{
    private const MAX_LINKS_PER_POST = 5;

    private const HTTP_TIMEOUT_SECONDS = 3;

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

        if ($host === 'youtu.be' || str_ends_with($host, '.youtube.com') || $host === 'youtube.com') {
            return SocialLinkPreview::PROVIDER_YOUTUBE;
        }

        if ($host === 'fb.watch' || str_ends_with($host, '.facebook.com') || $host === 'facebook.com') {
            return SocialLinkPreview::PROVIDER_FACEBOOK;
        }

        if (str_ends_with($host, '.tiktok.com') || $host === 'tiktok.com') {
            return SocialLinkPreview::PROVIDER_TIKTOK;
        }

        return null;
    }

    /**
     * @return array{provider: string, external_id: ?string, title: ?string, thumbnail_url: ?string, embed_url: ?string, is_live: bool}|null
     */
    public function resolveOne(string $url): ?array
    {
        $provider = $this->detectProvider($url);

        try {
            return match ($provider) {
                SocialLinkPreview::PROVIDER_YOUTUBE => $this->resolveYoutube($url),
                SocialLinkPreview::PROVIDER_FACEBOOK => $this->resolveFacebook($url),
                SocialLinkPreview::PROVIDER_TIKTOK => $this->resolveTiktok($url),
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
                'thumbnail_url' => $resolved['thumbnail_url'],
                'embed_url' => $resolved['embed_url'],
                'is_live' => $resolved['is_live'],
                'position' => $position++,
            ]);
        }

        $post->unsetRelation('linkPreviews');
    }

    private function extractYoutubeVideoId(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);

        if ($host === 'youtu.be') {
            $id = ltrim($path, '/');

            return $id !== '' ? $id : null;
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        if (isset($query['v']) && is_string($query['v']) && $query['v'] !== '') {
            return $query['v'];
        }

        if (preg_match('#/(?:live|shorts|embed)/([A-Za-z0-9_-]{6,})#', $path, $m)) {
            return $m[1];
        }

        return null;
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
                $snippet = $item['snippet'] ?? [];
                $thumbnails = $snippet['thumbnails'] ?? [];
                $thumbnail = $thumbnails['maxres']['url']
                    ?? $thumbnails['high']['url']
                    ?? $thumbnails['medium']['url']
                    ?? null;

                return [
                    'provider' => SocialLinkPreview::PROVIDER_YOUTUBE,
                    'external_id' => $videoId,
                    'title' => $snippet['title'] ?? null,
                    'thumbnail_url' => $thumbnail,
                    'embed_url' => "https://www.youtube.com/embed/{$videoId}",
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
            'title' => $oembed->json('title'),
            'thumbnail_url' => $oembed->json('thumbnail_url'),
            'embed_url' => "https://www.youtube.com/embed/{$videoId}",
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
}
