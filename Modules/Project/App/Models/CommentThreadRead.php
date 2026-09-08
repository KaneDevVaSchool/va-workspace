<?php

namespace Modules\Project\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * "Đã đọc đến đâu" của 1 user trên 1 luồng thảo luận (commentable
 * polymorphic Task/Project, cùng morph map với Comment). Dùng để tính
 * "còn bình luận chưa đọc của thành viên X" ở sidebar Thảo luận dự án —
 * xem CommentService::listFor()/markThreadRead().
 *
 * @property int $id
 * @property string $commentable_type
 * @property int $commentable_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $last_read_at
 */
class CommentThreadRead extends Model
{
    protected $fillable = [
        'commentable_type',
        'commentable_id',
        'user_id',
        'last_read_at',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
    ];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
