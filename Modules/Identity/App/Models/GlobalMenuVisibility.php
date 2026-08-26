<?php

namespace Modules\Identity\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ẩn/hiện 1 menu_key ở mức TOÀN HỆ THỐNG (superadmin) — không có row cho
 * 1 menu_key nghĩa là mặc định hiện (is_hidden=false). Đè lên trên
 * DepartmentSidebarConfig (per-department) khi is_hidden=true.
 *
 * @property int $id
 * @property string $menu_key
 * @property bool $is_hidden
 * @property int|null $updated_by
 */
class GlobalMenuVisibility extends Model
{
    protected $fillable = [
        'menu_key',
        'is_hidden',
        'updated_by',
    ];

    protected $casts = [
        'is_hidden' => 'boolean',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
