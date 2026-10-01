<?php

namespace Modules\Contract\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadSupplierDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ($this->user()->allows('contract.*') || $this->user()->allows('contract.manage_department'));
    }

    public function rules(): array
    {
        return [
            'document_type_id' => ['nullable', 'integer', 'exists:contract_supplier_document_types,id'],
            'custom_name' => ['nullable', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'no_expiry' => ['nullable', 'boolean'],
            'uploader_note' => ['nullable', 'string', 'max:5000'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
        ];
    }
}
