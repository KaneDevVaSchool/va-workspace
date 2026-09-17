<?php

namespace Modules\Project\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sprint (đợt chạy nước rút) — bảng RIÊNG, KHÔNG phải Task với type mới.
 * 1 Phase (Task type=phase) có nhiều Sprint qua phase_id. Task (type=task)
 * gán vào 1 Sprint qua tasks.sprint_id (quan hệ phẳng, không qua cây WBS).
 *
 * @property int $id
 * @property int $project_id
 * @property int $phase_id giai đoạn (tasks.type=phase) chứa sprint này
 * @property string|null $code
 * @property string $name
 * @property string|null $description
 * @property string $status planned | active | completed | cancelled
 * @property string|null $start_date
 * @property string|null $end_date
 * @property int $sort_order
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class Sprint extends Model
{
    protected $table = 'sprints';

    public const STATUSES = ['planned', 'active', 'completed', 'cancelled'];

    protected $fillable = [
        'project_id',
        'phase_id',
        'code',
        'name',
        'description',
        'status',
        'start_date',
        'end_date',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'sort_order' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'phase_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'sprint_id')->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
