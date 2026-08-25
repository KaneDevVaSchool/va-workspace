<?php

namespace Modules\Social\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int         $id
 * @property int         $user_id
 * @property string|null $content
 * @property array|null  $attachments
 * @property bool        $is_pinned
 * @property int|null    $pinned_by
 * @property \Illuminate\Support\Carbon|null $pinned_at
 * @property int|null    $shared_from_post_id
 */
class SocialPost extends Model
{
    use SoftDeletes;

    protected $table = 'social_posts';

    protected $fillable = [
        'user_id',
        'content',
        'attachments',
        'is_pinned',
        'pinned_by',
        'pinned_at',
        'shared_from_post_id',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_pinned' => 'boolean',
        'pinned_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pinnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }

    public function sharedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'shared_from_post_id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(SocialPostLike::class, 'post_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SocialPostComment::class, 'post_id');
    }
}
