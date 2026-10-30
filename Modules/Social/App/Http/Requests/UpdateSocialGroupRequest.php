<?php

namespace Modules\Social\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSocialGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:3', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'visibility' => ['sometimes', 'in:public,private'],
            'company_id' => ['sometimes', 'nullable', 'integer', 'exists:companies,id'],
            'cover' => ['sometimes', 'nullable', 'image', 'max:10240'],
            'avatar' => ['sometimes', 'nullable', 'image', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('company_id') && ($this->input('company_id') === '' || $this->input('company_id') === 'null')) {
            $this->merge(['company_id' => null]);
        }
    }

    public function messages(): array
    {
        return [
            'name.min' => 'Tên nhóm phải có ít nhất 3 ký tự.',
            'name.max' => 'Tên nhóm không được vượt quá 150 ký tự.',
            'description.max' => 'Mô tả nhóm không được vượt quá 2000 ký tự.',
            'cover.image' => 'Ảnh bìa không hợp lệ.',
            'cover.max' => 'Ảnh bìa không được vượt quá 10MB.',
            'avatar.image' => 'Ảnh đại diện không hợp lệ.',
            'avatar.max' => 'Ảnh đại diện không được vượt quá 5MB.',
        ];
    }
}
