<?php

namespace Modules\Social\App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Lưu ảnh/tệp đính kèm bài viết và bình luận lên Amazon S3 (disk `s3`,
 * cấu hình qua biến AWS_* trong .env) thay vì local disk của web server —
 * tránh phụ thuộc dung lượng ổ đĩa server và giới hạn upload của
 * OpenLiteSpeed/PHP khi nhiều người dùng đính kèm ảnh lớn cùng lúc.
 */
class SocialAttachmentUploader
{
    public function upload(UploadedFile $file, string $destDir = 'social/attachments'): array
    {
        $path = $file->store($this->prefixed($destDir), 's3');

        return [
            'path' => $path,
            'url' => Storage::disk('s3')->url($path),
            'size' => $file->getSize(),
        ];
    }

    private function prefixed(string $destDir): string
    {
        $base = trim((string) config('filesystems.s3_base_path'), '/');

        return $base === '' ? $destDir : $base.'/'.$destDir;
    }
}
