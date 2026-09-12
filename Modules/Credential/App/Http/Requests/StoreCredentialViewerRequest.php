<?php

namespace Modules\Credential\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCredentialViewerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Chọn người được cấp quyền xem.',
            'user_id.exists' => 'Người dùng không tồn tại.',
        ];
    }
}
