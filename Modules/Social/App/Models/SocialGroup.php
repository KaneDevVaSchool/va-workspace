<?php

namespace Modules\Social\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $visibility
 * @property string|null $cover_path
 * @property int $created_by
 */
class SocialGroup extends Model
{
    use SoftDeletes;

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITIES = [self::VISIBILITY_PUBLIC, self::VISIBILITY_PRIVATE];

    protected $table = 'social_groups';

    protected $fillable = [
        'name',
        'description',
        'visibility',
        'cover_path',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(SocialGroupMember::class, 'group_id');
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(SocialGroupJoinRequest::class, 'group_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(SocialPost::class, 'group_id');
    }
}
