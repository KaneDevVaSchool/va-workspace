<?php

namespace Modules\FeatureRequest\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFeatureRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:2000'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'page_url' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'Vui lòng mô tả yêu cầu cụ thể.',
            'description.max' => 'Mô tả không được quá 2000 ký tự.',
        ];
    }
}
