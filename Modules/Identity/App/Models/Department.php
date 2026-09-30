<?php

namespace Modules\Identity\App\Models;

use App\Models\User;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phòng ban — bảng phẳng có chủ đích (không cây cấp bậc), giữ nguyên cấu
 * trúc dù VA-HRM tổ chức OrgUnit dạng cây — vì permission scope trong toàn
 * dự án (vd. ProjectRepository::whereViewerDepartment()) so sánh trực tiếp
 * department_id integer. `hrm_org_unit_uuid`/`external_code`/`company_id`
 * chỉ dùng để tham chiếu/hiển thị, KHÔNG dùng trong điều kiện permission.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property string|null $hrm_org_unit_uuid
 * @property string|null $external_code
 * @property int|null $company_id
 */
class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'hrm_org_unit_uuid',
        'external_code',
        'company_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected static function newFactory(): Factory
    {
        return DepartmentFactory::new();
    }
}
