<?php

namespace Modules\DonTu\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DonTuWorkflow extends Model
{
    protected $table = 'don_tu_workflows';

    protected $fillable = [
        'code',
        'name',
        'description',
        'status_set_id',
        'department_id',
        'is_financial',
        'is_active',
        'initiator_policy',
        'closure_actor_policy',
        'closure_role',
        'created_by',
    ];

    protected $casts = [
        'is_financial' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function statusSet(): BelongsTo
    {
        return $this->belongsTo(DonTuStatusSet::class, 'status_set_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\App\Models\Department::class, 'department_id');
    }

    public function forms(): HasMany
    {
        return $this->hasMany(DonTuWorkflowForm::class, 'workflow_id')->orderBy('sequence');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(DonTuWorkflowStep::class, 'workflow_id')->orderBy('sort_order');
    }
}
