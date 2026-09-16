<?php

namespace Modules\Social\App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Proxy tìm kiếm GIF/sticker động qua GIPHY API (key giữ ở backend, không lộ
 * ra frontend) và tải GIF đã chọn về lưu tạm trong storage trước khi đính
 * kèm vào bài viết.
 */
class SocialGifService
{
    private const BASE_URL = 'https://api.giphy.com/v1';

    private const TEMP_DIR = 'social/gif-tmp';

    public function search(string $query, int $page, string $kind): array
    {
        $apiKey = $this->apiKey();
        $limit = 24;
        $offset = max(0, ($page - 1) * $limit);
        $endpoint = trim($query) === '' ? 'trending' : 'search';

        $response = Http::timeout(8)->get(self::BASE_URL.'/'.($kind === 'sticker' ? 'stickers' : 'gifs').'/'.$endpoint, array_filter([
            'api_key' => $apiKey,
            'q' => trim($query) !== '' ? $query : null,
            'limit' => $limit,
            'offset' => $offset,
            'rating' => 'g',
            'lang' => 'vi',
        ]));

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'q' => 'Không thể tìm kiếm GIF lúc này, vui lòng thử lại sau.',
            ]);
        }

        $items = collect($response->json('data', []))->map(fn (array $item) => $this->presentResult($item))->values();

        return [
            'results' => $items,
            'next_page' => $items->count() < $limit ? null : $page + 1,
        ];
    }

    private function presentResult(array $item): array
    {
        $images = $item['images'] ?? [];
        $preview = $images['fixed_width_small'] ?? $images['fixed_width'] ?? [];
        $full = $images['downsized_medium'] ?? $images['original'] ?? [];

        return [
            'id' => (string) ($item['id'] ?? ''),
            'title' => (string) ($item['title'] ?? ''),
            'preview_url' => (string) ($preview['url'] ?? ''),
            'preview_width' => (int) ($preview['width'] ?? 0),
            'preview_height' => (int) ($preview['height'] ?? 0),
            'download_url' => (string) ($full['url'] ?? ($images['original']['url'] ?? '')),
        ];
    }

    /**
     * Tải GIF từ URL GIPHY về lưu tạm, trả về descriptor để frontend giữ
     * lại và gửi kèm khi đăng bài (xem SocialPostService::attachPendingGifs()).
     */
    public function download(string $url): array
    {
        if (! $this->isAllowedGiphyUrl($url)) {
            throw ValidationException::withMessages([
                'url' => 'Đường dẫn GIF không hợp lệ.',
            ]);
        }

        $response = Http::timeout(15)->get($url);
        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'url' => 'Không thể tải GIF này, vui lòng chọn ảnh khác.',
            ]);
        }

        $body = $response->body();
        if (strlen($body) > 10 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'url' => 'GIF vượt quá dung lượng cho phép (10MB).',
            ]);
        }

        $token = (string) Str::uuid();
        $path = self::TEMP_DIR.'/'.$token.'.gif';
        Storage::disk('public')->put($path, $body);

        return [
            'token' => $token,
            'path' => $path,
            'name' => 'gif-'.$token.'.gif',
            'size' => strlen($body),
        ];
    }

    /**
     * Chuyển các GIF đã tải sẵn (qua download(), nằm tạm ở local disk
     * `public`/social/gif-tmp/) lên S3 vào thư mục đích (bài viết hoặc bình
     * luận), rồi xoá file tạm local. Bỏ qua token không hợp lệ/đã dọn dẹp.
     * Dùng chung cho SocialPostService và SocialCommentService.
     *
     * @param array<int, string> $tokens
     */
    public static function movePendingAttachments(array $tokens, string $destDir): array
    {
        $tempDisk = Storage::disk('public');
        $s3Disk = Storage::disk('s3');
        $attachments = [];

        foreach ($tokens as $token) {
            if (! is_string($token) || ! preg_match('/^[0-9a-f-]{36}$/i', $token)) {
                continue;
            }

            $sourcePath = self::TEMP_DIR.'/'.$token.'.gif';
            if (! $tempDisk->exists($sourcePath)) {
                continue;
            }

            $destPath = $destDir.'/gif-'.$token.'.gif';
            $s3Disk->put($destPath, $tempDisk->get($sourcePath));
            $tempDisk->delete($sourcePath);

            $attachments[] = [
                'type' => 'image',
                'name' => 'gif-'.$token.'.gif',
                'size' => $s3Disk->size($destPath),
                'path' => $destPath,
            ];
        }

        return $attachments;
    }

    private function isAllowedGiphyUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';

        return (bool) preg_match('/(^|\.)giphy\.com$/i', $host);
    }

    private function apiKey(): string
    {
        $key = (string) config('services.giphy.api_key');
        if ($key === '') {
            throw ValidationException::withMessages([
                'q' => 'Chưa cấu hình GIPHY_API_KEY trên máy chủ.',
            ]);
        }

        return $key;
    }
}
