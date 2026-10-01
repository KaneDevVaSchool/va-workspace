<?php

namespace Modules\Contract\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Identity\App\Models\Department;

class Supplier extends Model
{
    use SoftDeletes;

    protected $table = 'contract_suppliers';

    protected $fillable = [
        'code',
        'name',
        'short_name',
        'tax_code',
        'normalized_tax_code',
        'supplier_type_id',
        'supplier_group_id',
        'legal_name',
        'trade_name',
        'representative_name',
        'representative_title',
        'registered_address_line',
        'registered_ward',
        'registered_province',
        'transaction_address_same_as_registered',
        'transaction_address_line',
        'transaction_ward',
        'transaction_province',
        'phone',
        'email',
        'department_id',
        'owner_user_id',
        'status',
        'cooperation_started_at',
        'confirmed_by',
        'confirmed_at',
        'status_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'transaction_address_same_as_registered' => 'boolean',
        'cooperation_started_at' => 'date',
        'confirmed_at' => 'datetime',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(SupplierType::class, 'supplier_type_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SupplierGroup::class, 'supplier_group_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class, 'supplier_id');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(SupplierBankAccount::class, 'supplier_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SupplierDocument::class, 'supplier_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'supplier_id');
    }
}
