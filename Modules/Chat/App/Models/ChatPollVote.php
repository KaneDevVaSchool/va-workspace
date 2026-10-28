<?php

namespace Modules\Chat\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatPollVote extends Model
{
    protected $table = 'chat_poll_votes';

    protected $fillable = [
        'poll_id',
        'option_id',
        'user_id',
    ];

    public function option(): BelongsTo
    {
        return $this->belongsTo(ChatPollOption::class, 'option_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
