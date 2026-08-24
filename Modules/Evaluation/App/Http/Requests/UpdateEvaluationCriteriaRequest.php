<?php

namespace Modules\Evaluation\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEvaluationCriteriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'           => ['sometimes', 'required', 'string', 'max:255'],
            'type'           => ['sometimes', 'required', 'in:scale,behavior'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'levels'         => ['sometimes', 'required', 'array', 'min:1'],
            'levels.*.label' => ['required_with:levels', 'string', 'max:100'],
            'levels.*.score' => ['required_with:levels', 'integer'],
            'is_active'      => ['boolean'],
            'sort_order'     => ['integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'           => 'Tên tiêu chí là bắt buộc.',
            'name.max'                => 'Tên tiêu chí không được vượt quá 255 ký tự.',
            'type.in'                 => 'Kiểu tiêu chí phải là "scale" hoặc "behavior".',
            'levels.min'              => 'Tiêu chí phải có ít nhất 1 mức điểm.',
            'levels.*.label.required' => 'Nhãn mức điểm không được để trống.',
            'levels.*.score.integer'  => 'Điểm mức phải là số nguyên.',
        ];
    }
}
