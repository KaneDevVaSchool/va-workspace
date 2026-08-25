<?php

namespace Modules\Social\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSocialGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'visibility' => ['sometimes', 'in:public,private'],
            'cover' => ['sometimes', 'nullable', 'image', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên nhóm.',
            'name.min' => 'Tên nhóm phải có ít nhất 3 ký tự.',
            'name.max' => 'Tên nhóm không được vượt quá 150 ký tự.',
            'description.max' => 'Mô tả nhóm không được vượt quá 2000 ký tự.',
            'cover.image' => 'Ảnh bìa không hợp lệ.',
            'cover.max' => 'Ảnh bìa không được vượt quá 10MB.',
        ];
    }
}
