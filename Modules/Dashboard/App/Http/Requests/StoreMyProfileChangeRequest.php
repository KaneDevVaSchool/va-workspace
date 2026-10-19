<?php

namespace Modules\Dashboard\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMyProfileChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'changes' => ['required', 'array', 'min:1'],
            'changes.*' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'changes.required' => 'Vui lòng nhập ít nhất một trường cần chỉnh sửa.',
        ];
    }
}
