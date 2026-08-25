<?php

namespace Modules\Identity\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một thông báo in-app gửi tới user (mention bảng tin, …).
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $actor_id
 * @property string $type
 * @property string $title
 * @property string|null $body
 * @property string|null $url
 * @property array<string, mixed>|null $data
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class UserNotification extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'actor_id',
        'type',
        'title',
        'body',
        'url',
        'data',
        'read_at',
        'created_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }
}
