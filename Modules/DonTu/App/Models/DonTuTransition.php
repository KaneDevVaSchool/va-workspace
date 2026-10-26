<?php

namespace Modules\DonTu\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonTuTransition extends Model
{
    public $timestamps = false;

    protected $table = 'don_tu_transitions';

    protected $fillable = [
        'status_set_id',
        'from_code',
        'to_code',
        'action_key',
        'actor_policy',
        'guard_json',
    ];

    protected $casts = [
        'guard_json' => 'array',
    ];

    public function statusSet(): BelongsTo
    {
        return $this->belongsTo(DonTuStatusSet::class, 'status_set_id');
    }
}
