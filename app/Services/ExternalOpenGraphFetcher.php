<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Lấy og:title / description / image từ trang HTTPS bên ngoài (unfurl link).
 */
final class ExternalOpenGraphFetcher
{
    private const TIMEOUT_SECONDS = 4;

    private const MAX_BYTES = 524_288;

    /**
     * @return array{title: string, description: string, image: ?string, site_name: string}|null
     */
    public function fetch(string $url): ?array
    {
        $normalized = $this->normalizeUrl($url);
        if ($normalized === null || ! $this->isAllowedTarget($normalized)) {
            return null;
        }

        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->withHeaders([
                'User-Agent' => 'VA-Workspace-LinkPreview/1.0 (+https://vaschools.edu.vn)',
                'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
            ])
            ->withOptions(['allow_redirects' => ['max' => 3]])
            ->get($normalized);

        if (! $response->ok()) {
            return null;
        }

        $body = $response->body();
        if (strlen($body) > self::MAX_BYTES) {
            $body = substr($body, 0, self::MAX_BYTES);
        }

        $meta = $this->parseMeta($body);
        $host = parse_url($normalized, PHP_URL_HOST);

        $title = trim($meta['og:title'] ?? $meta['twitter:title'] ?? $meta['title'] ?? '');
        $description = trim($meta['og:description'] ?? $meta['twitter:description'] ?? $meta['description'] ?? '');
        $image = $this->absoluteAsset($normalized, $meta['og:image'] ?? $meta['twitter:image'] ?? null);
        $siteName = trim($meta['og:site_name'] ?? '');

        if ($title === '' && $description === '' && $image === null) {
            return null;
        }

        if ($title === '' && $description !== '') {
            $title = Str::limit($description, 120, '…');
        }

        return [
            'title' => $title !== '' ? $title : (is_string($host) ? $host : 'Liên kết'),
            'description' => Str::limit($description, 320, '…'),
            'image' => $image,
            'site_name' => $siteName !== '' ? $siteName : (is_string($host) ? $host : ''),
        ];
    }

    private function normalizeUrl(string $url): ?string
    {
        $url = rtrim(trim($url), ".,;:!?)'\"");
        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            return null;
        }

        return $url;
    }

    private function isAllowedTarget(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);
        if ($host === 'localhost' || str_ends_with($host, '.local')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! $this->isPrivateIp($host);
        }

        return true;
    }

    private function isPrivateIp(string $ip): bool
    {
        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }

    /**
     * @return array<string, string>
     */
    private function parseMeta(string $html): array
    {
        $out = [];

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $out['title'] = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
        }

        if (! preg_match_all('/<meta\s+([^>]+)>/i', $html, $tags)) {
            return $out;
        }

        foreach ($tags[1] as $attrs) {
            $property = null;
            $name = null;
            $content = null;

            if (preg_match('/property=["\']([^"\']+)["\']/i', $attrs, $m)) {
                $property = strtolower($m[1]);
            }
            if (preg_match('/name=["\']([^"\']+)["\']/i', $attrs, $m)) {
                $name = strtolower($m[1]);
            }
            if (preg_match('/content=["\']([^"\']*)["\']/i', $attrs, $m)) {
                $content = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
            }

            if ($content === null || $content === '') {
                continue;
            }

            if ($property !== null) {
                $out[$property] = $content;
            }
            if ($name === 'description') {
                $out['description'] = $content;
            }
            if ($name !== null && str_starts_with($name, 'twitter:')) {
                $out[$name] = $content;
            }
        }

        return $out;
    }

    private function absoluteAsset(string $pageUrl, ?string $asset): ?string
    {
        if (! is_string($asset) || trim($asset) === '') {
            return null;
        }

        $asset = trim(html_entity_decode($asset, ENT_QUOTES, 'UTF-8'));
        if (preg_match('#^https?://#i', $asset)) {
            return $asset;
        }

        $parts = parse_url($pageUrl);
        if (! is_array($parts)) {
            return null;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        if ($host === '') {
            return null;
        }

        if (str_starts_with($asset, '//')) {
            return $scheme.':'.$asset;
        }

        if (str_starts_with($asset, '/')) {
            return $scheme.'://'.$host.$asset;
        }

        $path = $parts['path'] ?? '/';
        $dir = str_contains($path, '/') ? substr($path, 0, (int) strrpos($path, '/')) : '';

        return $scheme.'://'.$host.$dir.'/'.$asset;
    }
}
