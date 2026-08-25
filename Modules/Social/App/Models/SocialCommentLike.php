<?php

namespace Modules\Social\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int    $id
 * @property int    $comment_id
 * @property int    $user_id
 * @property string $reaction_type
 */
class SocialCommentLike extends Model
{
    protected $table = 'social_comment_likes';

    protected $fillable = [
        'comment_id',
        'user_id',
        'reaction_type',
    ];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(SocialPostComment::class, 'comment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
