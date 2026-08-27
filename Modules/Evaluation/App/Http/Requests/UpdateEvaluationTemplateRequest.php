<?php

namespace Modules\Evaluation\App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Evaluation\App\Models\EvaluationTemplateCustomField;

class UpdateEvaluationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['boolean'],

            'criteria'                          => ['sometimes', 'required', 'array', 'min:1'],
            'criteria.*.evaluation_criteria_id' => ['required_with:criteria', 'integer'],
            'criteria.*.weight_percent'         => ['required_with:criteria', 'integer', 'min:0', 'max:100'],
            'criteria.*.required_score'         => ['nullable', 'integer'],
            'criteria.*.count_in_total'         => ['boolean'],

            'position_ids'   => ['sometimes', 'nullable', 'array'],
            'position_ids.*' => ['integer'],

            'custom_fields'               => ['sometimes', 'nullable', 'array'],
            'custom_fields.*.label'       => ['required', 'string', 'max:255'],
            'custom_fields.*.field_type'  => ['required', 'in:'.implode(',', EvaluationTemplateCustomField::FIELD_TYPES)],
            'custom_fields.*.options'     => ['nullable', 'array'],
            'custom_fields.*.options.*'   => ['string', 'max:255'],
            'custom_fields.*.is_required' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'        => 'Tên mẫu đánh giá là bắt buộc.',
            'name.max'             => 'Tên mẫu đánh giá không được vượt quá 255 ký tự.',
            'description.max'      => 'Mô tả không được vượt quá 1000 ký tự.',
            'criteria.min'         => 'Mẫu đánh giá phải có ít nhất 1 tiêu chí.',
            'criteria.*.weight_percent.required_with' => 'Trọng số không được để trống.',
            'criteria.*.weight_percent.min'           => 'Trọng số không hợp lệ.',
            'criteria.*.weight_percent.max'           => 'Trọng số tối đa là 100%.',
            'custom_fields.*.label.required'      => 'Trường tùy biến phải có nhãn hiển thị.',
            'custom_fields.*.field_type.required' => 'Loại trường tùy biến là bắt buộc.',
            'custom_fields.*.field_type.in'       => 'Loại trường tùy biến không hợp lệ.',
        ];
    }

    /**
     * Chỉ tiêu chí "tính vào tổng điểm" cộng trọng số — tổng nhóm đó phải
     * bằng 100%. Điểm phụ thêm (trường tùy biến kiểu bonus) nằm ngoài 100% này.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rows = $this->input('criteria');
            if (! is_array($rows) || empty($rows)) {
                return;
            }

            $counted = array_values(array_filter($rows, fn ($row) => is_array($row) && $this->countsInTotal($row)));
            if ($counted === []) {
                $validator->errors()->add('criteria', 'Phải có ít nhất 1 tiêu chí tính vào tổng điểm.');

                return;
            }

            foreach ($counted as $row) {
                $weight = (int) ($row['weight_percent'] ?? 0);
                if ($weight < 10 || $weight > 100 || $weight % 10 !== 0) {
                    $validator->errors()->add('criteria', 'Trọng số tiêu chí tính vào tổng điểm phải từ 10% đến 100%, bước 10.');

                    return;
                }
            }

            $total = array_sum(array_map(fn ($row) => (int) ($row['weight_percent'] ?? 0), $counted));
            if ($total !== 100) {
                $validator->errors()->add('criteria', 'Tổng trọng số các tiêu chí tính vào tổng điểm phải bằng 100%.');
            }
        });
    }

    /** @param  array<string, mixed>  $row */
    private function countsInTotal(array $row): bool
    {
        if (! array_key_exists('count_in_total', $row)) {
            return true;
        }

        return filter_var($row['count_in_total'], FILTER_VALIDATE_BOOLEAN);
    }
}
