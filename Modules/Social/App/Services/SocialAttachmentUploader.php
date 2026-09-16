<?php

namespace Modules\Social\App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Lưu ảnh/tệp đính kèm bài viết và bình luận lên Amazon S3 (disk `s3`,
 * cấu hình qua biến AWS_* trong .env) thay vì local disk của web server —
 * tránh phụ thuộc dung lượng ổ đĩa server và giới hạn upload của
 * OpenLiteSpeed/PHP khi nhiều người dùng đính kèm ảnh lớn cùng lúc.
 */
class SocialAttachmentUploader
{
    /**
     * Không đặt ACL 'public' theo object — bucket dùng bucket policy để cho
     * đọc public trong prefix social/ (xem docs/s3-bucket-policy.json), vì
     * bucket bật Block Public Access chặn ACL public theo object.
     */
    public function upload(UploadedFile $file, string $destDir = 'social/attachments'): array
    {
        $this->ensureS3Configured();

        $disk = Storage::disk('s3');
        $path = $file->store($this->prefixed($destDir), 's3');

        if (! is_string($path) || $path === '' || ! $disk->exists($path)) {
            throw ValidationException::withMessages([
                'attachments' => ['Không thể tải tệp lên lúc này. Vui lòng thử lại sau hoặc liên hệ quản trị nếu lỗi lặp lại.'],
            ]);
        }

        return [
            'path' => $path,
            'url' => $disk->url($path),
            'size' => $file->getSize(),
        ];
    }

    private function ensureS3Configured(): void
    {
        $missing = collect([
            'AWS_ACCESS_KEY_ID' => config('filesystems.disks.s3.key'),
            'AWS_SECRET_ACCESS_KEY' => config('filesystems.disks.s3.secret'),
            'AWS_BUCKET' => config('filesystems.disks.s3.bucket'),
            'AWS_DEFAULT_REGION' => config('filesystems.disks.s3.region'),
        ])->filter(fn ($value) => ! filled($value));

        if ($missing->isEmpty()) {
            return;
        }

        throw ValidationException::withMessages([
            'attachments' => ['Máy chủ chưa cấu hình lưu trữ ảnh (S3). Vui lòng liên hệ quản trị hệ thống.'],
        ]);
    }

    private function prefixed(string $destDir): string
    {
        $base = trim((string) config('filesystems.s3_base_path'), '/');

        return $base === '' ? $destDir : $base.'/'.$destDir;
    }
}
