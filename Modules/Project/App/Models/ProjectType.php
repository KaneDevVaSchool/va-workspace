<?php

namespace Modules\Project\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Loại dự án — danh mục tự do dùng chung toàn hệ thống (mục A). Người dùng
 * chọn từ danh sách có sẵn hoặc tự tạo loại mới ngay trong form (nút "+"),
 * loại mới tạo được tái dùng cho các dự án sau — xem ProjectLabel làm mẫu.
 *
 * @property int         $id
 * @property string      $name
 * @property int|null    $created_by
 */
class ProjectType extends Model
{
    protected $table = 'project_types';

    protected $fillable = [
        'name',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
