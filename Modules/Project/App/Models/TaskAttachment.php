<?php

namespace Modules\Project\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Đính kèm của Task — khuôn theo ProjectAttachment, rút gọn chỉ file upload
 * (không kind/drive_link/url như Project).
 *
 * @property int         $id
 * @property int         $task_id
 * @property string      $file_path
 * @property string      $file_name
 * @property int|null    $file_size
 * @property int|null    $uploaded_by
 */
class TaskAttachment extends Model
{
    protected $table = 'task_attachments';

    protected $fillable = [
        'task_id',
        'file_path',
        'file_name',
        'file_size',
        'uploaded_by',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
