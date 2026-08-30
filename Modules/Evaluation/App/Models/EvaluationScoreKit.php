<?php

namespace Modules\Evaluation\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\App\Models\Department;

/**
 * Cấu hình cách tính điểm của một phòng ban (1 dòng / phòng).
 *
 * @property int         $id
 * @property int         $department_id
 * @property string|null $mode  base_adjust | weighted_task
 * @property float       $base_score
 * @property float       $points_per_completed_task
 * @property float       $points_per_incomplete_task
 * @property bool        $use_project_importance
 * @property int|null    $classification_criterion_id
 * @property int|null    $created_by
 * @property int|null    $updated_by
 */
class EvaluationScoreKit extends Model
{
    public const MODE_BASE_ADJUST = 'base_adjust';

    public const MODE_WEIGHTED_TASK = 'weighted_task';

    public const MODES = [
        self::MODE_BASE_ADJUST,
        self::MODE_WEIGHTED_TASK,
    ];

    protected $table = 'evaluation_score_kits';

    protected $fillable = [
        'department_id',
        'mode',
        'base_score',
        'points_per_completed_task',
        'points_per_incomplete_task',
        'use_project_importance',
        'classification_criterion_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'base_score' => 'decimal:2',
        'points_per_completed_task' => 'decimal:2',
        'points_per_incomplete_task' => 'decimal:2',
        'use_project_importance' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function classificationCriterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriteria::class, 'classification_criterion_id');
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
