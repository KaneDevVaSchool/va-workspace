<?php

namespace Modules\Identity\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShortcutRequest extends FormRequest
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
            'path' => ['required', 'string', 'max:512', 'regex:/^\/(?!\/).*/'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Vui lòng nhập tên lối tắt.',
            'path.required' => 'Thiếu đường dẫn trang.',
            'path.regex' => 'Đường dẫn trang không hợp lệ.',
        ];
    }
}
