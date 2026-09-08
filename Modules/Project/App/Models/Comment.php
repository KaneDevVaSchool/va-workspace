<?php

namespace Modules\Project\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bình luận (Thảo luận) — polymorphic dùng chung cho Task và Project.
 * Rút gọn từ Modules\Social\App\Models\SocialPostComment: bỏ hashtag/sticker,
 * tách attachments ra bảng riêng (comment_attachments) thay vì JSON.
 *
 * @property int $id
 * @property string $commentable_type
 * @property int $commentable_id
 * @property int|null $parent_comment_id
 * @property int $user_id
 * @property int|null $mentioned_user_id
 * @property string $content
 * @property bool $is_pinned
 * @property \Illuminate\Support\Carbon|null $pinned_at
 */
class Comment extends Model
{
    use SoftDeletes;

    /** Đồng bộ với ProjectServiceProvider::boot() Relation::enforceMorphMap(). */
    public const REACTION_TYPES = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];

    protected $fillable = [
        'commentable_type',
        'commentable_id',
        'parent_comment_id',
        'user_id',
        'mentioned_user_id',
        'content',
        'is_pinned',
        'pinned_at',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'pinned_at' => 'datetime',
    ];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
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

    public function attachments(): HasMany
    {
        return $this->hasMany(CommentAttachment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(CommentReaction::class);
    }
}
