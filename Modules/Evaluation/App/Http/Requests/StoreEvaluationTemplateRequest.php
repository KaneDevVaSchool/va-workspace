<?php

namespace Modules\Evaluation\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Evaluation\App\Models\EvaluationTemplateCriterion;
use Modules\Evaluation\App\Models\EvaluationTemplateCustomField;

class StoreEvaluationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware + Controller đã kiểm tra quyền.
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['boolean'],

            'criteria'                        => ['required', 'array', 'min:1'],
            'criteria.*.evaluation_criteria_id' => ['required', 'integer'],
            'criteria.*.weight_label'         => ['required', 'in:'.implode(',', array_keys(EvaluationTemplateCriterion::WEIGHT_MAP))],
            'criteria.*.required_score'       => ['nullable', 'integer', 'min:0'],
            'criteria.*.count_in_total'       => ['boolean'],

            'position_ids'   => ['nullable', 'array'],
            'position_ids.*' => ['integer'],

            'custom_fields'                    => ['nullable', 'array'],
            'custom_fields.*.label'             => ['required', 'string', 'max:255'],
            'custom_fields.*.field_type'        => ['required', 'in:'.implode(',', EvaluationTemplateCustomField::FIELD_TYPES)],
            'custom_fields.*.options'           => ['nullable', 'array'],
            'custom_fields.*.options.*'         => ['string', 'max:255'],
            'custom_fields.*.is_required'       => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'                              => 'Tên mẫu đánh giá là bắt buộc.',
            'name.max'                                    => 'Tên mẫu đánh giá không được vượt quá 255 ký tự.',
            'description.max'                             => 'Mô tả không được vượt quá 1000 ký tự.',
            'criteria.required'                           => 'Mẫu đánh giá phải có ít nhất 1 tiêu chí.',
            'criteria.min'                                => 'Mẫu đánh giá phải có ít nhất 1 tiêu chí.',
            'criteria.*.evaluation_criteria_id.required'  => 'Tiêu chí đánh giá không được để trống.',
            'criteria.*.weight_label.required'            => 'Trọng số không được để trống.',
            'criteria.*.weight_label.in'                  => 'Trọng số không hợp lệ.',
            'custom_fields.*.label.required'              => 'Trường tùy biến phải có nhãn hiển thị.',
            'custom_fields.*.field_type.required'         => 'Loại trường tùy biến là bắt buộc.',
            'custom_fields.*.field_type.in'               => 'Loại trường tùy biến không hợp lệ.',
        ];
    }
}
