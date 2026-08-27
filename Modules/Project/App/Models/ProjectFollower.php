<?php

namespace Modules\Project\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1 dòng "theo dõi dự án" — user tự thêm/bỏ theo dõi (mục B).
 *
 * @property int $id
 * @property int $project_id
 * @property int $user_id
 */
class ProjectFollower extends Model
{
    protected $table = 'project_followers';

    protected $fillable = [
        'project_id',
        'user_id',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
