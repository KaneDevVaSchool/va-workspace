<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadProjectTestCaseAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm tra qua middleware route ('permission:test_case.manage')
    }

    public function rules(): array
    {
        return [
            'attachment' => ['required', 'file', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ];
    }

    public function messages(): array
    {
        return [
            'attachment.required' => 'Vui lòng chọn ảnh.',
            'attachment.image' => 'Tệp đính kèm phải là hình ảnh.',
            'attachment.max' => 'Ảnh không được vượt quá 5MB.',
            'attachment.mimes' => 'Định dạng ảnh không được hỗ trợ.',
        ];
    }
}
