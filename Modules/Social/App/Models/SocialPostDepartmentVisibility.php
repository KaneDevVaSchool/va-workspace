<?php

namespace Modules\Social\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\App\Models\Department;

/**
 * @property int $id
 * @property int $post_id
 * @property int $department_id
 */
class SocialPostDepartmentVisibility extends Model
{
    protected $table = 'social_post_department_visibility';

    protected $fillable = [
        'post_id',
        'department_id',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'post_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
