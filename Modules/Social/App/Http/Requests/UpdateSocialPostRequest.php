<?php

namespace Modules\Social\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSocialPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:8000'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Nội dung bài viết không được để trống.',
            'content.max' => 'Nội dung bài viết không được vượt quá 8000 ký tự.',
        ];
    }
}
