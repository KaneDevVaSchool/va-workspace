<?php

namespace Modules\Identity\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShortcutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Vui lòng nhập tên lối tắt.',
        ];
    }
}
