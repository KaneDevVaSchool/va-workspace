<?php

namespace Modules\Dashboard\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $utility_key
 * @property string $label
 * @property string $icon
 * @property string $tone
 * @property string $link_type
 * @property string|null $link_target
 * @property int $sort_order
 * @property bool $is_visible
 * @property bool $is_under_maintenance
 * @property int|null $updated_by
 */
class DashboardQuickUtility extends Model
{
    protected $fillable = [
        'utility_key',
        'label',
        'icon',
        'tone',
        'link_type',
        'link_target',
        'sort_order',
        'is_visible',
        'is_under_maintenance',
        'updated_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_visible' => 'boolean',
        'is_under_maintenance' => 'boolean',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
