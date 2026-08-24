<?php

namespace Modules\Identity\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Vai trò hệ thống (RBAC tối giản — chỉ role, chưa có permission chi tiết).
 * 7 role cố định theo docs/VA_WORKSPACE_OVERVIEW.md §4.1, seed trong
 * Database/Seeders/RoleSeeder.php.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 */
class Role extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
