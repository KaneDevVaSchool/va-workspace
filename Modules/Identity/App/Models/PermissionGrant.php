<?php

namespace Modules\Identity\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DB override cho PermissionCatalog — super_admin ghi vào đây để
 * mở rộng/thu hồi quyền của 1 role theo scope cụ thể.
 *
 * @property int    $id
 * @property string $role_code
 * @property string $permission_key
 * @property bool   $granted         true = cấp, false = thu hồi
 * @property string $scope_type      global | department | team
 * @property int|null $scope_id      department_id hoặc team_id
 * @property int|null $created_by
 */
class PermissionGrant extends Model
{
    protected $fillable = [
        'role_code',
        'permission_key',
        'granted',
        'scope_type',
        'scope_id',
        'created_by',
    ];

    protected $casts = [
        'granted' => 'boolean',
        'scope_id' => 'integer',
        'created_by' => 'integer',
    ];
}
