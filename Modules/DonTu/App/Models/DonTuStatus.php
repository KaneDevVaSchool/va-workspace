<?php

namespace Modules\DonTu\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonTuStatus extends Model
{
    public $timestamps = false;

    protected $table = 'don_tu_statuses';

    protected $fillable = [
        'status_set_id',
        'code',
        'label',
        'group_kind',
        'terminal_kind',
        'sort_order',
    ];

    public function statusSet(): BelongsTo
    {
        return $this->belongsTo(DonTuStatusSet::class, 'status_set_id');
    }
}
