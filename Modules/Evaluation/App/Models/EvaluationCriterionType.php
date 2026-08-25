<?php

namespace Modules\Evaluation\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Identity\App\Models\Department;

/**
 * @property int         $id
 * @property int         $department_id
 * @property string      $name
 * @property string      $code
 * @property string|null $description
 * @property int         $sort_order
 * @property int|null    $created_by
 */
class EvaluationCriterionType extends Model
{
    protected $table = 'evaluation_criterion_types';

    protected $fillable = [
        'department_id',
        'name',
        'code',
        'description',
        'sort_order',
        'created_by',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(EvaluationCriteria::class, 'criterion_type_id');
    }
}
