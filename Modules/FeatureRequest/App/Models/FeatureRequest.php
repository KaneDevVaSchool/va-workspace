<?php

namespace Modules\FeatureRequest\App\Models;

use App\Models\User;
use Modules\Identity\App\Models\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEWING = 'reviewing';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_DONE = 'done';

    protected $fillable = [
        'created_by',
        'department_id',
        'page_title',
        'page_url',
        'description',
        'status',
        'reviewed_by',
        'reviewed_at',
        'expected_done_at',
        'progress_note',
        'reject_reason',
        'done_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'expected_done_at' => 'date',
        'done_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
