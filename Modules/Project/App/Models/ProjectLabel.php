<?php

namespace Modules\Project\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Nhãn tự do gán cho dự án — dùng chung toàn hệ thống (mục E).
 *
 * @property int         $id
 * @property string      $name
 * @property string      $color        primary | success | info | warning | danger
 * @property int|null    $created_by
 */
class ProjectLabel extends Model
{
    protected $table = 'project_labels';

    public const COLORS = ['primary', 'success', 'info', 'warning', 'danger'];

    protected $fillable = [
        'name',
        'color',
        'created_by',
    ];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_label_assignments', 'project_label_id', 'project_id')
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
