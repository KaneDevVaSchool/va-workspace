<?php

namespace Modules\Contract\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierDocumentRequirement extends Model
{
    protected $table = 'contract_supplier_document_requirements';

    protected $fillable = [
        'document_type_id',
        'supplier_type_id',
        'supplier_group_id',
        'requirement',
        'reviewer_role',
    ];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(SupplierDocumentType::class, 'document_type_id');
    }
}
