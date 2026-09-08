<?php

namespace Modules\Project\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Thư mục tài liệu của dự án — cây lồng nhau qua parent_id.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $parent_id
 * @property string $name
 * @property int|null $created_by
 */
class ProjectFolder extends Model
{
    protected $table = 'project_folders';

    protected $fillable = [
        'project_id',
        'parent_id',
        'name',
        'created_by',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class, 'folder_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
