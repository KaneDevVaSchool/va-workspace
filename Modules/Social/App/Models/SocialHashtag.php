<?php

namespace Modules\Social\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $label
 * @property int $posts_count
 * @property \Illuminate\Support\Carbon|null $last_used_at
 */
class SocialHashtag extends Model
{
    protected $table = 'social_hashtags';

    protected $fillable = [
        'name',
        'label',
        'posts_count',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(SocialPost::class, 'social_hashtag_post', 'hashtag_id', 'post_id');
    }
}
