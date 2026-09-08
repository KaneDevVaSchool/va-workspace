<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'original_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'original_name.required' => 'Tên tệp không được để trống.',
            'original_name.max' => 'Tên tệp không được vượt quá 255 ký tự.',
        ];
    }
}
