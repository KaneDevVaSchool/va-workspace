<?php

namespace Modules\Report\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Report\App\Models\Report;

class StorePersonnelEvaluationReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'period_type' => ['required', 'string', Rule::in(Report::PERIOD_TYPES)],
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
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
            'title.required' => 'Chưa đặt tên báo cáo.',
            'title.max' => 'Tên báo cáo không được quá 150 ký tự.',
            'period_type.in' => 'Kiểu kỳ báo cáo không hợp lệ.',
            'period_from.required' => 'Chưa chọn ngày bắt đầu kỳ.',
            'period_to.required' => 'Chưa chọn ngày kết thúc kỳ.',
            'period_to.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
        ];
    }
}
