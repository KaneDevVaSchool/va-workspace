<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UploadProjectAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware + Controller đã kiểm tra quyền.
    }

    public function rules(): array
    {
        return [
            'file' => [
                'nullable', 'file', 'max:20480',
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar,txt,csv',
            ],
            'url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'File tải lên không được vượt quá 20MB.',
            'file.mimes' => 'Định dạng file không được hỗ trợ.',
            'url.url' => 'Link Google Drive không hợp lệ.',
        ];
    }

    /** Phải có ít nhất 1 trong 2: file hoặc url. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->hasFile('file') && ! trim((string) $this->input('url'))) {
                $validator->errors()->add('file', 'Cần chọn file hoặc nhập link Google Drive.');
            }
        });
    }
}
