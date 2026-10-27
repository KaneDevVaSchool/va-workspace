<?php

namespace Modules\DonTu\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DonTuStatusSet extends Model
{
    protected $table = 'don_tu_status_sets';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function statuses(): HasMany
    {
        return $this->hasMany(DonTuStatus::class, 'status_set_id')->orderBy('sort_order');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(DonTuTransition::class, 'status_set_id');
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(DonTuWorkflow::class, 'status_set_id');
    }
}
