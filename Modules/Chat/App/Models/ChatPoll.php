<?php

namespace Modules\Chat\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatPoll extends Model
{
    protected $table = 'chat_polls';

    protected $fillable = [
        'message_id',
        'question',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ChatPollOption::class, 'poll_id')->orderBy('position')->orderBy('id');
    }
}
