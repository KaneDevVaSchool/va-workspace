<?php

namespace Modules\Social\App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Lưu ảnh/tệp đính kèm bài viết và bình luận trên local disk `public`,
 * cùng cơ chế với avatar/cover nhóm (SocialGroupService) và ảnh bình chọn
 * (SocialPollService).
 */
class SocialAttachmentUploader
{
    public function upload(UploadedFile $file, string $destDir = 'social/attachments'): array
    {
        $path = $file->store($destDir, 'public');

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'size' => $file->getSize(),
        ];
    }
}
