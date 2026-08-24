<?php

namespace Modules\Identity\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một dòng nhật ký thao tác (đăng nhập, đổi quyền, nhóm, xem thử vai trò…).
 *
 * @property int $id
 * @property int|null $actor_id
 * @property string|null $actor_name
 * @property string|null $actor_email
 * @property string $action
 * @property string $description
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $properties
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'actor_name',
        'actor_email',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'properties',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'subject_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
