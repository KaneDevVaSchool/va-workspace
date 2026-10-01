<?php

namespace Modules\Contract\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Contract\App\Enums\ContractEnums;

class ReviewSupplierDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ($this->user()->allows('contract.*') || $this->user()->allows('contract.manage_department'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([
                ContractEnums::DOCUMENT_VALID,
                ContractEnums::DOCUMENT_NEEDS_SUPPLEMENT,
                ContractEnums::DOCUMENT_INVALID,
            ])],
            'review_note' => ['nullable', 'required_if:status,'.ContractEnums::DOCUMENT_NEEDS_SUPPLEMENT.','.ContractEnums::DOCUMENT_INVALID, 'string', 'max:5000'],
        ];
    }
}
