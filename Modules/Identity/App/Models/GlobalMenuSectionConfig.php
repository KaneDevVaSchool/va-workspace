<?php

namespace Modules\Identity\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tên tuỳ chỉnh của section menu sidebar Ở MỨC TOÀN HỆ THỐNG (superadmin).
 * 1 row mỗi section_key nếu đã từng đổi tên — không có row = dùng nhãn mặc định.
 *
 * @property int $id
 * @property string $section_key
 * @property string|null $custom_label
 * @property int|null $updated_by
 */
class GlobalMenuSectionConfig extends Model
{
    protected $fillable = [
        'section_key',
        'custom_label',
        'updated_by',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
