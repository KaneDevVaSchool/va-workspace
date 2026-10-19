<?php

namespace Modules\Dashboard\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardProfileChangeRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    protected $table = 'dashboard_profile_change_requests';

    protected $fillable = [
        'user_id',
        'changes',
        'note',
        'status',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
