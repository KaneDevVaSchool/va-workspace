<?php

namespace Modules\DonTu\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonTuFormTemplate extends Model
{
    protected $table = 'don_tu_form_templates';

    protected $fillable = [
        'code',
        'name',
        'description',
        'scope',
        'department_id',
        'status',
        'version',
        'schema_json',
        'branding_json',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'schema_json' => 'array',
        'branding_json' => 'array',
        'version' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(\Modules\Identity\App\Models\Department::class, 'department_id');
    }
}
