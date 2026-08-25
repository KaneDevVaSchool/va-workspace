<?php

namespace Modules\Social\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int    $id
 * @property int    $post_id
 * @property int    $user_id
 * @property string $reaction_type
 */
class SocialPostLike extends Model
{
    public const REACTION_TYPES = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];

    protected $table = 'social_post_likes';

    protected $fillable = [
        'post_id',
        'user_id',
        'reaction_type',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
