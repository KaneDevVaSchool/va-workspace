<?php

namespace Modules\Report\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Report\App\Models\Report;

class UpdateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:150'],
            'period_type' => ['sometimes', 'string', Rule::in(Report::PERIOD_TYPES)],
            'period_from' => ['sometimes', 'date'],
            'period_to' => ['sometimes', 'date', 'after_or_equal:period_from'],
            'filter_user_ids' => ['sometimes', 'array'],
            'filter_user_ids.*' => ['integer'],
            'viewer_user_ids' => ['sometimes', 'array'],
            'viewer_user_ids.*' => ['integer'],
            'column_keys' => ['sometimes', 'array'],
            'column_keys.*' => ['string', 'max:60'],
            'criterion_ids' => ['sometimes', 'array'],
            'criterion_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.max' => 'Tên báo cáo không được quá 150 ký tự.',
            'period_to.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
        ];
    }
}
