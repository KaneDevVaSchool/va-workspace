<?php

namespace Modules\Contract\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ($this->user()->allows('contract.*') || $this->user()->allows('contract.manage_department'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:255'],
            'tax_code' => ['nullable', 'string', 'max:30'],
            'supplier_type_id' => ['nullable', 'integer', 'exists:contract_supplier_types,id'],
            'supplier_group_id' => ['nullable', 'integer', 'exists:contract_supplier_groups,id'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'representative_name' => ['nullable', 'string', 'max:255'],
            'representative_title' => ['nullable', 'string', 'max:255'],
            'registered_address_line' => ['nullable', 'string', 'max:255'],
            'registered_ward' => ['nullable', 'string', 'max:255'],
            'registered_province' => ['nullable', 'string', 'max:255'],
            'transaction_address_same_as_registered' => ['nullable', 'boolean'],
            'transaction_address_line' => ['nullable', 'string', 'max:255'],
            'transaction_ward' => ['nullable', 'string', 'max:255'],
            'transaction_province' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.contact_type' => ['nullable', 'string', 'max:50'],
            'contacts.*.full_name' => ['nullable', 'string', 'max:255'],
            'contacts.*.position' => ['nullable', 'string', 'max:255'],
            'contacts.*.department' => ['nullable', 'string', 'max:255'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:50'],
            'contacts.*.is_primary' => ['nullable', 'boolean'],
            'bank_accounts' => ['nullable', 'array'],
            'bank_accounts.*.bank_name' => ['nullable', 'string', 'max:255'],
            'bank_accounts.*.branch' => ['nullable', 'string', 'max:255'],
            'bank_accounts.*.account_holder' => ['nullable', 'string', 'max:255'],
            'bank_accounts.*.account_number' => ['nullable', 'string', 'max:255'],
            'bank_accounts.*.currency' => ['nullable', 'string', 'max:10'],
            'bank_accounts.*.is_default' => ['nullable', 'boolean'],
            'bank_accounts.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên nhà cung cấp là bắt buộc.',
            'email.email' => 'Email không đúng định dạng.',
            'contacts.*.email.email' => 'Email người liên hệ không đúng định dạng.',
        ];
    }
}
