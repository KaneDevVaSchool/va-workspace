<?php

namespace Modules\Contract\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierBankAccount extends Model
{
    protected $table = 'contract_supplier_bank_accounts';

    protected $fillable = [
        'supplier_id',
        'bank_name',
        'branch',
        'account_holder',
        'account_number',
        'currency',
        'is_default',
        'notes',
    ];

    protected $casts = ['is_default' => 'boolean'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
