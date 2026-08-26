<?php

namespace Modules\Identity\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Override hiển thị menu sidebar cho 1 phòng ban — không có row cho 1
 * cặp (department_id, menu_key) nghĩa là mặc định hiện (is_visible=true).
 *
 * @property int $id
 * @property int $department_id
 * @property string $menu_key
 * @property bool $is_visible
 * @property string|null $custom_label
 * @property int|null $sort_order
 * @property string|null $section_key
 * @property int|null $updated_by
 */
class DepartmentSidebarConfig extends Model
{
    protected $fillable = [
        'department_id',
        'menu_key',
        'is_visible',
        'custom_label',
        'sort_order',
        'section_key',
        'updated_by',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
