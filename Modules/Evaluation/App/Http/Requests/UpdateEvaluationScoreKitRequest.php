<?php

namespace Modules\Evaluation\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Models\EvaluationScoreKit;

class UpdateEvaluationScoreKitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('classification_criterion_id') && $this->input('classification_criterion_id') === '') {
            $this->merge(['classification_criterion_id' => null]);
        }
        if ($this->has('mode') && $this->input('mode') === '') {
            $this->merge(['mode' => null]);
        }
    }

    public function rules(): array
    {
        $departmentId = (int) $this->user()?->department_id;

        return [
            'mode' => ['nullable', 'string', Rule::in(EvaluationScoreKit::MODES)],
            'base_score' => ['sometimes', 'numeric', 'min:0', 'max:9999'],
            'points_per_completed_task' => ['sometimes', 'numeric', 'min:-999', 'max:999'],
            'points_per_incomplete_task' => ['sometimes', 'numeric', 'min:-999', 'max:999'],
            'use_project_importance' => ['sometimes', 'boolean'],
            'classification_criterion_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($departmentId) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if ($departmentId < 1) {
                        return;
                    }
                    $criterion = EvaluationCriteria::query()
                        ->where('id', (int) $value)
                        ->where('department_id', $departmentId)
                        ->first();
                    if ($criterion === null) {
                        $fail('Thang phân loại phải thuộc phòng ban này.');

                        return;
                    }
                    if ($criterion->type !== 'scale') {
                        $fail('Thang phân loại phải là tiêu chí thang điểm.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'mode.in' => 'Cách tính điểm không hợp lệ.',
            'base_score.numeric' => 'Điểm khởi đầu phải là số.',
            'base_score.min' => 'Điểm khởi đầu không được âm.',
            'base_score.max' => 'Điểm khởi đầu quá lớn.',
            'points_per_completed_task.numeric' => 'Điểm mỗi việc hoàn thành phải là số.',
            'points_per_incomplete_task.numeric' => 'Điểm mỗi việc chưa hoàn thành phải là số.',
        ];
    }
}
