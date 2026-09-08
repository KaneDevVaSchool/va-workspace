<?php

namespace Modules\Project\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Đính kèm của dự án — file upload, ảnh (thư viện ảnh riêng), hoặc link Drive.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $folder_id
 * @property string $kind file | drive_link | image
 * @property string|null $file_path
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property string|null $url
 * @property int|null $uploaded_by
 */
class ProjectAttachment extends Model
{
    protected $table = 'project_attachments';

    protected $fillable = [
        'project_id',
        'folder_id',
        'kind',
        'file_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'url',
        'uploaded_by',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(ProjectFolder::class, 'folder_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
