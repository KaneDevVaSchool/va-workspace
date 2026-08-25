<?php

namespace Modules\Evaluation\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvaluationCriterionTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'code'        => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9]*$/'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên loại tiêu chí là bắt buộc.',
            'name.max'      => 'Tên loại tiêu chí không được vượt quá 255 ký tự.',
            'code.max'      => 'Mã loại tiêu chí không được vượt quá 40 ký tự.',
            'code.regex'    => 'Mã loại tiêu chí chỉ gồm chữ và số, ví dụ TCA0001.',
            'description.max' => 'Mô tả không được vượt quá 1000 ký tự.',
        ];
    }
}
