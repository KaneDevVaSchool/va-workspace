<?php

namespace Modules\Evaluation\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEvaluationPositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('evaluation_positions', 'name')->ignore($this->route('id')),
            ],
            'kind'        => ['sometimes', 'required', Rule::in(['position', 'department'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => 'Tên vị trí đánh giá là bắt buộc.',
            'name.max'         => 'Tên vị trí đánh giá không được vượt quá 255 ký tự.',
            'name.unique'      => 'Vị trí đánh giá này đã tồn tại.',
            'kind.required'    => 'Loại vị trí đánh giá là bắt buộc.',
            'kind.in'          => 'Loại vị trí đánh giá không hợp lệ.',
            'description.max'  => 'Mô tả không được vượt quá 1000 ký tự.',
        ];
    }
}
