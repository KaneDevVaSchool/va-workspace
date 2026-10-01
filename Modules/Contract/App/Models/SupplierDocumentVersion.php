<?php

namespace Modules\Contract\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierDocumentVersion extends Model
{
    protected $table = 'contract_supplier_document_versions';

    protected $fillable = [
        'supplier_document_id',
        'version',
        'original_name',
        'path',
        'mime_type',
        'size_bytes',
        'status',
        'uploader_note',
        'review_note',
        'uploaded_by',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(SupplierDocument::class, 'supplier_document_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
