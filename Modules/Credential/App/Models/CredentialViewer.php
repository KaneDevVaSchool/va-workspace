<?php

namespace Modules\Credential\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $credential_id
 * @property int $user_id
 * @property int|null $granted_by
 */
class CredentialViewer extends Model
{
    protected $table = 'credential_viewers';

    protected $fillable = [
        'credential_id',
        'user_id',
        'granted_by',
    ];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
