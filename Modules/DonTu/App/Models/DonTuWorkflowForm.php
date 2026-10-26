<?php

namespace Modules\DonTu\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonTuWorkflowForm extends Model
{
    public $timestamps = false;

    protected $table = 'don_tu_workflow_forms';

    protected $fillable = [
        'workflow_id',
        'form_template_id',
        'form_key',
        'sequence',
        'is_start',
    ];

    protected $casts = [
        'is_start' => 'boolean',
        'sequence' => 'integer',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(DonTuWorkflow::class, 'workflow_id');
    }

    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(DonTuFormTemplate::class, 'form_template_id');
    }
}
