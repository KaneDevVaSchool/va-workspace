<?php

namespace Modules\Identity\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nhóm (team) — thuộc 1 phòng ban cố định. Dữ liệu sở hữu lâu dài của
 * Workspace (KHÔNG phải dữ liệu tạm chờ HRM như Department/User).
 * `team_lead_id` luôn do Workspace tự gán tay, không sync từ HRM.
 *
 * @property int $id
 * @property int $department_id
 * @property string $name
 * @property int|null $team_lead_id
 * @property string|null $hrm_team_uuid  Tham chiếu đối chiếu, KHÔNG phải nguồn sự thật
 */
class Team extends Model
{
    protected $fillable = [
        'department_id',
        'name',
        'team_lead_id',
        'hrm_team_uuid',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function teamLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_lead_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
