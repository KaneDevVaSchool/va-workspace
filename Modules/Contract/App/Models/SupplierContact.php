<?php

namespace Modules\Contract\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierContact extends Model
{
    protected $table = 'contract_supplier_contacts';

    protected $fillable = [
        'supplier_id',
        'contact_type',
        'full_name',
        'position',
        'department',
        'email',
        'phone',
        'is_primary',
    ];

    protected $casts = ['is_primary' => 'boolean'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
