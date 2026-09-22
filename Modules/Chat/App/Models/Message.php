<?php

namespace Modules\Chat\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $sender_id
 * @property string $message
 * @property string|null $sticker_id
 * @property string $message_type
 * @property int|null $reply_to_id
 * @property \Illuminate\Support\Carbon|null $edited_at
 * @property \Illuminate\Support\Carbon|null $recalled_at
 * @property \Illuminate\Support\Carbon $created_at
 */
class Message extends Model
{
    protected $table = 'messages';

    public const TYPE_TEXT = 'text';

    public const TYPE_IMAGE = 'image';

    public const TYPE_FILE = 'file';

    public const TYPE_SYSTEM = 'system';

    public const TYPE_STICKER = 'sticker';

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'reply_to_id',
        'message',
        'sticker_id',
        'message_type',
        'edited_at',
        'recalled_at',
    ];

    protected $casts = [
        'edited_at' => 'datetime',
        'recalled_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function hides(): HasMany
    {
        return $this->hasMany(MessageHide::class, 'message_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class, 'message_id');
    }

    public function isRecalled(): bool
    {
        return $this->recalled_at !== null;
    }
}
