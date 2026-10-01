<?php

namespace Modules\Contract\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierGroup extends Model
{
    protected $table = 'contract_supplier_groups';

    protected $fillable = ['supplier_type_id', 'code', 'name', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(SupplierType::class, 'supplier_type_id');
    }
}
