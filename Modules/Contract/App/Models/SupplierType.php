<?php

namespace Modules\Contract\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierType extends Model
{
    protected $table = 'contract_supplier_types';

    protected $fillable = ['code', 'name', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function groups(): HasMany
    {
        return $this->hasMany(SupplierGroup::class, 'supplier_type_id');
    }
}
