<?php

namespace Modules\Contract\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Identity\App\Models\Department;

class Contract extends Model
{
    use SoftDeletes;

    protected $table = 'contracts';

    protected $fillable = [
        'supplier_id',
        'code',
        'contract_number',
        'title',
        'type',
        'status',
        'department_id',
        'owner_user_id',
        'signed_at',
        'starts_at',
        'ends_at',
        'value_before_tax',
        'tax_rate',
        'value_after_tax',
        'currency',
    ];

    protected $casts = [
        'signed_at' => 'date',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'value_before_tax' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'value_after_tax' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
