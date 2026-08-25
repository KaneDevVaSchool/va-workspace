<?php

namespace Modules\Social\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $post_id
 * @property string|null $title
 * @property string|null $content
 * @property string|null $image_path
 * @property bool $allow_multiple
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property bool $is_closed
 */
class SocialPoll extends Model
{
    protected $table = 'social_polls';

    protected $fillable = [
        'post_id',
        'title',
        'content',
        'image_path',
        'allow_multiple',
        'ends_at',
        'is_closed',
    ];

    protected $casts = [
        'allow_multiple' => 'boolean',
        'is_closed' => 'boolean',
        'ends_at' => 'datetime',
    ];

    public function isEnded(): bool
    {
        return $this->is_closed || ($this->ends_at !== null && $this->ends_at->isPast());
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'post_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(SocialPollOption::class, 'poll_id')->orderBy('position')->orderBy('id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(SocialPollVote::class, 'poll_id');
    }
}
