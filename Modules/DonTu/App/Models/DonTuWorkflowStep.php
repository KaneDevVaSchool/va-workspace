<?php

namespace Modules\DonTu\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonTuWorkflowStep extends Model
{
    public $timestamps = false;

    protected $table = 'don_tu_workflow_steps';

    protected $fillable = [
        'workflow_id',
        'sort_order',
        'name',
        'status_group_code',
        'step_type',
        'target_form_keys',
        'assignee_policy',
        'assignee_role',
        'assignee_user_id',
        'sla_hours',
        'sign_mode',
        'workspace_task',
        'allow_reject',
        'allow_request_changes',
        'blocks_cancel',
    ];

    protected $casts = [
        'target_form_keys' => 'array',
        'workspace_task' => 'boolean',
        'allow_reject' => 'boolean',
        'allow_request_changes' => 'boolean',
        'blocks_cancel' => 'boolean',
        'sla_hours' => 'integer',
        'sort_order' => 'integer',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(DonTuWorkflow::class, 'workflow_id');
    }
}
