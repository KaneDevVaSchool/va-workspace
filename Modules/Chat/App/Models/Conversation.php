<?php

namespace Modules\Chat\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $type
 */
class Conversation extends Model
{
    protected $table = 'conversations';

    public const TYPE_PRIVATE = 'private';

    public const TYPE_GROUP = 'group';

    protected $fillable = [
        'type',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(ConversationMember::class, 'conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class, 'conversation_id')->latestOfMany();
    }
}
