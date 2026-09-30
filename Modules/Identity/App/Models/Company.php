<?php

namespace Modules\Identity\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Công ty — đồng bộ từ VA-HRM (bảng tra cứu/hiển thị thuần, không tham gia
 * điều kiện permission nào). Xem Modules/Identity/App/Hrm/Services/HrmEmployeeSyncService.php.
 *
 * @property int $id
 * @property string $hrm_uuid
 * @property string $code
 * @property string $name
 * @property bool $is_active
 */
class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'hrm_uuid',
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
