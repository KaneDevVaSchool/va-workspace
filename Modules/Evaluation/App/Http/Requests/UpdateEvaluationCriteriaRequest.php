<?php

namespace Modules\Evaluation\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEvaluationCriteriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowHalf = $this->boolean('allow_half');
        $step = $allowHalf ? '0.5' : '1';
        $min = $allowHalf ? '0.5' : '1';

        return [
            'name'              => ['sometimes', 'required', 'string', 'max:255'],
            'type'              => ['sometimes', 'required', 'in:scale,behavior'],
            'criterion_type_id' => ['nullable', 'integer'],
            'description'       => ['nullable', 'string', 'max:1000'],
            'levels'               => ['sometimes', 'required', 'array', 'min:1'],
            'levels.*.code'        => ['nullable', 'string', 'max:20'],
            'levels.*.label'       => ['required_with:levels', 'string', 'max:100'],
            'levels.*.description' => ['nullable', 'string', 'max:255'],
            'levels.*.score'       => [
                'required_with:levels',
                'numeric',
                "multiple_of:{$step}",
                Rule::when($this->input('type') === 'scale', ["min:{$min}"]),
            ],
            'is_active'      => ['boolean'],
            'allow_half'     => ['boolean'],
            'sort_order'     => ['integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        $allowHalf = $this->boolean('allow_half');

        return [
            'name.required'           => 'Tên tiêu chí là bắt buộc.',
            'name.max'                => 'Tên tiêu chí không được vượt quá 255 ký tự.',
            'type.in'                 => 'Kiểu tiêu chí phải là "scale" hoặc "behavior".',
            'levels.min'              => 'Tiêu chí phải có ít nhất 1 mức điểm.',
            'levels.*.label.required' => 'Nhãn mức không được để trống.',
            'levels.*.score.numeric'     => $allowHalf
                ? 'Trọng số mức phải là số, bước 0.5.'
                : 'Trọng số mức phải là số nguyên.',
            'levels.*.score.multiple_of' => $allowHalf
                ? 'Trọng số mức phải là bội số của 0.5.'
                : 'Trọng số mức phải là số nguyên.',
        ];
    }
}
