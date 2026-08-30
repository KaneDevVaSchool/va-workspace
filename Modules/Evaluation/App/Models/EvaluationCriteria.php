<?php

namespace Modules\Evaluation\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\App\Models\Department;

/**
 * @property int         $id
 * @property int         $department_id
 * @property string      $name
 * @property string      $type           'scale' | 'behavior'
 * @property string|null $description
 * @property array       $levels         [{code, label, description, score}]
 * @property bool        $is_active
 * @property bool        $allow_half          cho phép trọng số bước 0.5
 * @property bool        $use_in_evaluation   hiện trên trang ĐGNL của thành viên
 * @property bool        $use_for_task_type   nguồn mức độ quan trọng / loại công việc của phòng ban
 * @property int         $sort_order
 * @property int|null    $created_by
 * @property int|null    $updated_by
 */
class EvaluationCriteria extends Model
{
    protected $table = 'evaluation_criteria';

    public const WITH_PRESENT = ['criterionType', 'department', 'creator.department', 'updater.department'];

    protected $fillable = [
        'department_id',
        'criterion_type_id',
        'name',
        'type',
        'description',
        'levels',
        'is_active',
        'allow_half',
        'use_in_evaluation',
        'use_for_task_type',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'levels'             => 'array',
        'is_active'          => 'boolean',
        'allow_half'         => 'boolean',
        'use_in_evaluation'  => 'boolean',
        'use_for_task_type'  => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function criterionType(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriterionType::class, 'criterion_type_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Điểm tối đa của tiêu chí:
     *   scale    → mức điểm cao nhất trong levels.
     *   behavior → tổng điểm dương (đóng góp tối đa có thể đạt).
     */
    public function getMaxScoreAttribute(): float
    {
        $levels = $this->levels ?? [];

        if ($this->type === 'scale') {
            return (float) collect($levels)->max('score');
        }

        return (float) collect($levels)->where('score', '>', 0)->sum('score');
    }
}
