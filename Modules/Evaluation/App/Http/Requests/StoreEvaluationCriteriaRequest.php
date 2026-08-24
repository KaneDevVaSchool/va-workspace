<?php

namespace Modules\Evaluation\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvaluationCriteriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware đã kiểm tra auth + permission.
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'type'           => ['required', 'in:scale,behavior'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'levels'         => ['required', 'array', 'min:1'],
            'levels.*.label' => ['required', 'string', 'max:100'],
            'levels.*.score' => ['required', 'integer'],
            'is_active'      => ['boolean'],
            'sort_order'     => ['integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'           => 'Tên tiêu chí là bắt buộc.',
            'name.max'                => 'Tên tiêu chí không được vượt quá 255 ký tự.',
            'type.required'           => 'Kiểu tiêu chí là bắt buộc.',
            'type.in'                 => 'Kiểu tiêu chí phải là "scale" hoặc "behavior".',
            'description.max'         => 'Mô tả không được vượt quá 1000 ký tự.',
            'levels.required'         => 'Tiêu chí phải có ít nhất 1 mức điểm.',
            'levels.min'              => 'Tiêu chí phải có ít nhất 1 mức điểm.',
            'levels.*.label.required' => 'Nhãn mức điểm không được để trống.',
            'levels.*.score.required' => 'Điểm mức không được để trống.',
            'levels.*.score.integer'  => 'Điểm mức phải là số nguyên.',
        ];
    }
}
