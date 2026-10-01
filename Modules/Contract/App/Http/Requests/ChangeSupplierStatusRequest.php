<?php

namespace Modules\Contract\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Contract\App\Enums\ContractEnums;

class ChangeSupplierStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ($this->user()->allows('contract.*') || $this->user()->allows('contract.manage_department'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(ContractEnums::SUPPLIER_STATUSES)],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
