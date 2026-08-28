<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm tra qua middleware route ('permission:task.create')
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required', 'file', 'max:20480',
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar,txt,csv',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Cần chọn file để đính kèm.',
            'file.max' => 'File tải lên không được vượt quá 20MB.',
            'file.mimes' => 'Định dạng file không được hỗ trợ.',
        ];
    }
}
