<?php

namespace Modules\Credential\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $category
 * @property string|null $icon
 * @property bool $is_active
 */
class CredentialProvider extends Model
{
    protected $table = 'credential_providers';

    protected $fillable = [
        'name',
        'category',
        'icon',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function credentials(): HasMany
    {
        return $this->hasMany(Credential::class, 'provider_id');
    }
}
