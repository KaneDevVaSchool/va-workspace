<?php

namespace Modules\Contract\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractAuditLog extends Model
{
    protected $table = 'contract_audit_logs';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'subject_code',
        'action',
        'field',
        'old_value',
        'new_value',
        'is_sensitive',
        'description',
        'actor_id',
    ];

    protected $casts = ['is_sensitive' => 'boolean'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
