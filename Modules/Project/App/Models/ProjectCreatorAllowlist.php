<?php

namespace Modules\Project\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1 dòng trong danh sách nhân sự được phép tạo dự án ngoài các role đã có
 * sẵn 'project.create' (mục C) — chỉ MỞ RỘNG quyền, không thu hẹp.
 *
 * @property int $id
 * @property int $user_id
 */
class ProjectCreatorAllowlist extends Model
{
    protected $table = 'project_creator_allowlist';

    protected $fillable = [
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
