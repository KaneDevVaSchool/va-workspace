<?php

namespace Modules\Evaluation\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\App\Models\Department;
use Modules\Project\App\Models\Task;

/**
 * Một lần áp dụng mức tiêu chí hành vi cho một nhân sự (điểm cộng hoặc trừ).
 *
 * `score` mang dấu: dương là điểm cộng, âm là điểm trừ. Tên tiêu chí, tên mức
 * và điểm đều được chụp tại thời điểm ghi nhận nên vẫn hiển thị đúng dù danh
 * mục tiêu chí bị sửa hoặc xoá về sau.
 *
 * @property int         $id
 * @property int         $department_id
 * @property int         $user_id
 * @property int|null    $criterion_id
 * @property array|null  $criterion_snapshot  {name, criterion_type_id, criterion_type_name}
 * @property string|null $level_code
 * @property string|null $level_label
 * @property float       $score
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property string|null $reason
 * @property string|null $evidence_path
 * @property int|null    $task_id
 * @property string      $status  pending | approved | rejected
 * @property int|null    $recorded_by
 * @property int|null    $approved_by
 */
class EvaluationEvent extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const WITH_PRESENT = ['user', 'criterion', 'recorder', 'approver', 'task'];

    protected $table = 'evaluation_events';

    protected $fillable = [
        'department_id',
        'user_id',
        'criterion_id',
        'criterion_snapshot',
        'level_code',
        'level_label',
        'score',
        'occurred_at',
        'reason',
        'evidence_path',
        'task_id',
        'status',
        'recorded_by',
        'approved_by',
        'approved_at',
        'reject_reason',
    ];

    protected $casts = [
        'criterion_snapshot' => 'array',
        'score' => 'decimal:2',
        'occurred_at' => 'date',
        'approved_at' => 'datetime',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriteria::class, 'criterion_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isBonus(): bool
    {
        return (float) $this->score > 0;
    }
}
