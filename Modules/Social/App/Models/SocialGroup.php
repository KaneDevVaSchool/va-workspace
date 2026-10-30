<?php

namespace Modules\Social\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Identity\App\Models\Company;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $visibility
 * @property int|null $company_id
 * @property string|null $cover_path
 * @property string|null $avatar_path
 * @property int $created_by
 */
class SocialGroup extends Model
{
    use SoftDeletes;

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITIES = [self::VISIBILITY_PUBLIC, self::VISIBILITY_PRIVATE];

    /**
     * Mã pháp nhân trên Workspace (đồng bộ HRM): Việt Mỹ, Viễn Đông, Hoàng Việt, CIT.
     * Viễn Đông thương mại là VIDON, không phải Savitech.
     */
    public const LEGAL_ENTITY_CODES = [
        'VM', 'VAS',
        'VID', 'VIDON', 'VIDON CORP',
        'HV', 'HVID', 'HVID EDU.CORP',
        'CIT',
    ];

    protected $table = 'social_groups';

    protected $fillable = [
        'name',
        'description',
        'visibility',
        'company_id',
        'cover_path',
        'avatar_path',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function companyIdOf(User $user): ?int
    {
        $id = $user->company_id ?? $user->department?->company_id;

        return $id !== null && (int) $id > 0 ? (int) $id : null;
    }

    public function includesCompanyOf(User $user): bool
    {
        $companyId = self::companyIdOf($user);

        return $this->company_id !== null
            && $companyId !== null
            && (int) $this->company_id === $companyId;
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
