<?php

namespace Modules\Evaluation\App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Identity\App\Models\Department;

/**
 * @property int         $id
 * @property int         $department_id
 * @property string      $name
 * @property string      $type           'scale' | 'behavior'
 * @property string|null $description
 * @property array       $levels         [{label, score}]
 * @property bool        $is_active
 * @property int         $sort_order
 * @property int|null    $created_by
 */
class EvaluationCriteria extends Model
{
    protected $table = 'evaluation_criteria';

    protected $fillable = [
        'department_id',
        'name',
        'type',
        'description',
        'levels',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'levels'    => 'array',
        'is_active' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Điểm tối đa của tiêu chí:
     *   scale    → mức điểm cao nhất trong levels.
     *   behavior → tổng điểm dương (đóng góp tối đa có thể đạt).
     */
    public function getMaxScoreAttribute(): int
    {
        $levels = $this->levels ?? [];

        if ($this->type === 'scale') {
            return (int) collect($levels)->max('score');
        }

        return (int) collect($levels)->where('score', '>', 0)->sum('score');
    }
}
