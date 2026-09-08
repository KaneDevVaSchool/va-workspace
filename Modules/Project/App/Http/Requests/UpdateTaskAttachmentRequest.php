<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm tra qua middleware route ('permission:task.create')
    }

    public function rules(): array
    {
        return [
            'file_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'file_name.required' => 'Tên tệp không được để trống.',
            'file_name.max' => 'Tên tệp không được vượt quá 255 ký tự.',
        ];
    }
}
