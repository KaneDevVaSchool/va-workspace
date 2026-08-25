<?php

namespace Modules\Social\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int         $id
 * @property int         $post_id
 * @property int|null    $parent_comment_id
 * @property int         $user_id
 * @property int|null    $mentioned_user_id
 * @property string      $content
 * @property array|null  $attachments
 */
class SocialPostComment extends Model
{
    use SoftDeletes;

    protected $table = 'social_post_comments';

    protected $fillable = [
        'post_id',
        'parent_comment_id',
        'user_id',
        'mentioned_user_id',
        'content',
        'attachments',
    ];

    protected $casts = [
        'attachments' => 'array',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mentionedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_comment_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_comment_id')->orderBy('created_at');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(SocialCommentLike::class, 'comment_id');
    }
}
