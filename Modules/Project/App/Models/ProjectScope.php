<?php

namespace Modules\Project\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\App\Models\Department;

/**
 * 1 dòng phạm vi triển khai của dự án — kèm % tỷ trọng KPI.
 *
 * @property int         $id
 * @property int         $project_id
 * @property string      $scope_type       head_office_bt_llq | ht | kv | department
 * @property int|null    $department_id    chỉ có giá trị khi scope_type = department
 * @property float       $weight_percent
 */
class ProjectScope extends Model
{
    protected $table = 'project_scopes';

    protected $fillable = [
        'project_id',
        'scope_type',
        'department_id',
        'weight_percent',
    ];

    protected $casts = [
        'weight_percent' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
