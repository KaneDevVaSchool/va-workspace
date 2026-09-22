<?php

namespace Modules\Chat\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $sender_id
 * @property string $message
 * @property string $message_type
 * @property \Illuminate\Support\Carbon $created_at
 */
class Message extends Model
{
    protected $table = 'messages';

    public const TYPE_TEXT = 'text';

    public const TYPE_IMAGE = 'image';

    public const TYPE_FILE = 'file';

    public const TYPE_SYSTEM = 'system';

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'message',
        'message_type',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
