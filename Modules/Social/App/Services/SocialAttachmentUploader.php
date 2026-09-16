<?php

namespace Modules\Social\App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Đẩy ảnh/tệp đính kèm bài viết lên Amazon S3 thông qua service va-pictures
 * (Go, riêng biệt — xem docs/API.md của va-pictures, mục POST /api/v1/upload)
 * thay vì lưu trên local disk của web server. Tránh giới hạn
 * client_max_body_size/upload_max_filesize của nginx/PHP gây
 * ERR_CONNECTION_RESET khi người dùng đính kèm ảnh dung lượng lớn.
 */
class SocialAttachmentUploader
{
    public function upload(UploadedFile $file): array
    {
        $response = Http::withHeaders($this->authHeaders())
            ->timeout(30)
            ->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())
            ->post($this->baseUrl().'/api/v1/upload');

        if (! $response->successful() || ! $response->json('success')) {
            throw ValidationException::withMessages([
                'attachments' => [$this->extractErrorMessage($response)],
            ]);
        }

        $data = $response->json('data', []);

        return [
            'url' => (string) ($data['url'] ?? ''),
            'key' => (string) ($data['key'] ?? ''),
            'size' => (int) ($data['size'] ?? $file->getSize()),
        ];
    }

    private function extractErrorMessage(\Illuminate\Http\Client\Response $response): string
    {
        $message = $response->json('error.message');

        return is_string($message) && $message !== ''
            ? $message
            : 'Không thể tải tệp lên lúc này, vui lòng thử lại sau.';
    }

    private function authHeaders(): array
    {
        $apiKey = (string) config('services.va_pictures.api_key');

        return $apiKey !== '' ? ['X-API-Key' => $apiKey] : [];
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.va_pictures.base_url'), '/');
    }
}
