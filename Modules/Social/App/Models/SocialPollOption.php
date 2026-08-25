<?php

namespace Modules\Social\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $poll_id
 * @property string $label
 * @property int $position
 * @property int|null $votes_count
 */
class SocialPollOption extends Model
{
    protected $table = 'social_poll_options';

    protected $fillable = [
        'poll_id',
        'label',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(SocialPoll::class, 'poll_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(SocialPollVote::class, 'option_id');
    }
}
