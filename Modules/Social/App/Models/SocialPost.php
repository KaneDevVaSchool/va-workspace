<?php

namespace Modules\Social\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Identity\App\Models\Department;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $department_id
 * @property int|null $wall_user_id
 * @property string|null $content
 * @property \Illuminate\Support\Carbon|null $content_updated_at
 * @property array|null $attachments
 * @property bool $is_pinned
 * @property string|null $pin_scope
 * @property int|null $pinned_by
 * @property \Illuminate\Support\Carbon|null $pinned_at
 * @property int|null $shared_from_post_id
 */
class SocialPost extends Model
{
    use SoftDeletes;

    protected $table = 'social_posts';

    protected $fillable = [
        'user_id',
        'department_id',
        'wall_user_id',
        'content',
        'content_updated_at',
        'attachments',
        'is_pinned',
        'pin_scope',
        'pinned_by',
        'pinned_at',
        'shared_from_post_id',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_pinned' => 'boolean',
        'pinned_at' => 'datetime',
        'content_updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function wallUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wall_user_id');
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

    public function revisions(): HasMany
    {
        return $this->hasMany(SocialPostRevision::class, 'post_id');
    }

    public function poll(): HasOne
    {
        return $this->hasOne(SocialPoll::class, 'post_id');
    }
}
