<?php

namespace Modules\Project\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mục nhanh gắn vào dự án từ menu chuột phải (danh mục / công việc /
 * phase / baseline / hồ sơ ký số) — lưu tạm cho đến khi module Task đầy đủ.
 *
 * @property int         $id
 * @property int         $project_id
 * @property string      $kind
 * @property string      $title
 * @property array|null  $payload
 * @property int|null    $created_by
 */
class ProjectQuickItem extends Model
{
    public const KIND_TASK_CATEGORY = 'task_category';

    public const KIND_TASK = 'task';

    public const KIND_PHASE = 'phase';

    public const KIND_BASELINE = 'baseline';

    public const KIND_SIGNATURE = 'signature';

    public const KINDS = [
        self::KIND_TASK_CATEGORY,
        self::KIND_TASK,
        self::KIND_PHASE,
        self::KIND_BASELINE,
        self::KIND_SIGNATURE,
    ];

    public const WORK_KINDS = [
        self::KIND_TASK_CATEGORY,
        self::KIND_TASK,
        self::KIND_PHASE,
    ];

    protected $table = 'project_quick_items';

    protected $fillable = [
        'project_id',
        'kind',
        'title',
        'payload',
        'created_by',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
