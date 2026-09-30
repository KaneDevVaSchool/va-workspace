<?php

namespace Modules\Identity\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Chức vụ kiêm nhiệm đồng bộ từ VA-HRM (AssignmentResource.is_primary=false).
 * Không có khoá tự nhiên ổn định từ HRM cho từng dòng — chiến lược sync là
 * xoá hết + insert lại toàn bộ của 1 user mỗi lần đồng bộ, xem
 * HrmEmployeeSyncService::applyToUser().
 *
 * @property int $id
 * @property int $user_id
 * @property string $job_title_name
 * @property string|null $company_name
 * @property string|null $org_unit_name
 * @property string|null $org_unit_path
 * @property \Illuminate\Support\Carbon|null $effective_from
 * @property \Illuminate\Support\Carbon|null $effective_to
 */
class UserConcurrentPosition extends Model
{
    protected $table = 'user_concurrent_positions';

    protected $fillable = [
        'user_id',
        'job_title_name',
        'company_name',
        'org_unit_name',
        'org_unit_path',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
