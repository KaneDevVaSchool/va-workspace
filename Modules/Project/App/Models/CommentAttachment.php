<?php

namespace Modules\Project\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Đính kèm của bình luận — cấu trúc như TaskAttachment (chỉ file upload).
 *
 * @property int $id
 * @property int $comment_id
 * @property string $file_path
 * @property string $file_name
 * @property int|null $file_size
 * @property string $type 'image'|'file'
 */
class CommentAttachment extends Model
{
    protected $fillable = [
        'comment_id',
        'file_path',
        'file_name',
        'file_size',
        'type',
    ];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }
}
