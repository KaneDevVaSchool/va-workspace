<?php

namespace Modules\Contract\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierDocumentType extends Model
{
    protected $table = 'contract_supplier_document_types';

    protected $fillable = [
        'code',
        'name',
        'group_label',
        'has_expiry',
        'allows_no_expiry',
        'requires_review',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'has_expiry' => 'boolean',
        'allows_no_expiry' => 'boolean',
        'requires_review' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function requirements(): HasMany
    {
        return $this->hasMany(SupplierDocumentRequirement::class, 'document_type_id');
    }
}
