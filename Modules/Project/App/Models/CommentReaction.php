<?php

namespace Modules\Project\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Thả cảm xúc cho bình luận — tương đương SocialCommentLike.
 *
 * @property int $id
 * @property int $comment_id
 * @property int $user_id
 * @property string $reaction_type
 */
class CommentReaction extends Model
{
    protected $fillable = [
        'comment_id',
        'user_id',
        'reaction_type',
    ];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
