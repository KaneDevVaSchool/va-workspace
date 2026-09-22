<?php

namespace Modules\Chat\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $message_id
 * @property string $file_path
 * @property string $file_name
 * @property int $file_size
 * @property string|null $mime_type
 * @property string $type
 */
class MessageAttachment extends Model
{
    protected $table = 'message_attachments';

    public const TYPE_IMAGE = 'image';

    public const TYPE_FILE = 'file';

    protected $fillable = [
        'message_id',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'type',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
