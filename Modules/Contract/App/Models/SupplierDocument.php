<?php

namespace Modules\Contract\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierDocument extends Model
{
    protected $table = 'contract_supplier_documents';

    protected $fillable = [
        'supplier_id',
        'document_type_id',
        'custom_name',
        'status',
        'current_version',
        'document_number',
        'issuer',
        'issued_at',
        'expires_at',
        'no_expiry',
        'uploader_note',
        'review_note',
        'uploaded_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
        'no_expiry' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(SupplierDocumentType::class, 'document_type_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SupplierDocumentVersion::class, 'supplier_document_id');
    }
}
