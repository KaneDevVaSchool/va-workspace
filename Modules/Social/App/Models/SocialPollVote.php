<?php

namespace Modules\Social\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $poll_id
 * @property int $option_id
 * @property int $user_id
 */
class SocialPollVote extends Model
{
    protected $table = 'social_poll_votes';

    protected $fillable = [
        'poll_id',
        'option_id',
        'user_id',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(SocialPoll::class, 'poll_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(SocialPollOption::class, 'option_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
