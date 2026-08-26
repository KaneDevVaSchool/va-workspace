<?php

namespace Modules\Evaluation\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationCriteriaRepositoryInterface;

class StoreEvaluationCriteriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware đã kiểm tra auth + permission.
    }

    public function rules(): array
    {
        $allowHalf = $this->boolean('allow_half');
        $step = $allowHalf ? '0.5' : '1';
        $min = $allowHalf ? '0.5' : '1';
        $departmentId = (int) $this->user()?->department_id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($departmentId) {
                    if ($departmentId < 1) {
                        return;
                    }
                    $repository = app(EvaluationCriteriaRepositoryInterface::class);
                    if ($repository->existsNameInDepartment($departmentId, (string) $value)) {
                        $fail('Tên tiêu chí này đã tồn tại trong phòng ban, vui lòng đặt tên khác.');
                    }
                },
            ],
            'type' => ['required', 'in:scale,behavior'],
            'criterion_type_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:1000'],
            'levels' => ['required', 'array', 'min:1'],
            'levels.*.code' => ['nullable', 'string', 'max:20'],
            'levels.*.label' => ['required', 'string', 'max:100'],
            'levels.*.description' => ['nullable', 'string', 'max:255'],
            'levels.*.score' => [
                'required',
                'numeric',
                "multiple_of:{$step}",
                Rule::when($this->input('type') === 'scale', ["min:{$min}"]),
            ],
            'is_active' => ['boolean'],
            'allow_half' => ['boolean'],
            'use_in_evaluation' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        $allowHalf = $this->boolean('allow_half');

        return [
            'name.required' => 'Tên tiêu chí là bắt buộc.',
            'name.max' => 'Tên tiêu chí không được vượt quá 255 ký tự.',
            'type.required' => 'Kiểu tiêu chí là bắt buộc.',
            'type.in' => 'Kiểu tiêu chí phải là "scale" hoặc "behavior".',
            'description.max' => 'Mô tả không được vượt quá 1000 ký tự.',
            'levels.required' => 'Tiêu chí phải có ít nhất 1 mức điểm.',
            'levels.min' => 'Tiêu chí phải có ít nhất 1 mức điểm.',
            'levels.*.label.required' => 'Nhãn mức không được để trống.',
            'levels.*.code.max' => 'Mã mức không được vượt quá 20 ký tự.',
            'levels.*.description.max' => 'Mô tả ngắn không được vượt quá 255 ký tự.',
            'levels.*.score.required' => 'Trọng số mức không được để trống.',
            'levels.*.score.numeric' => $allowHalf
                ? 'Trọng số mức phải là số, bước 0.5.'
                : 'Trọng số mức phải là số nguyên.',
            'levels.*.score.multiple_of' => $allowHalf
                ? 'Trọng số mức phải là bội số của 0.5.'
                : 'Trọng số mức phải là số nguyên.',
            'levels.*.score.min' => $allowHalf
                ? 'Trọng số thang điểm tối thiểu là 0.5.'
                : 'Trọng số thang điểm tối thiểu là 1.',
        ];
    }
}
