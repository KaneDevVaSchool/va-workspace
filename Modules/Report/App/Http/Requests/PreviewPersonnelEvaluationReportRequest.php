<?php

namespace Modules\Report\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Xem trước số liệu trước khi tạo báo cáo — chỉ cần kỳ và phạm vi nhân sự,
 * không cần tên hay cột hiển thị.
 */
class PreviewPersonnelEvaluationReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'filter_user_ids' => ['sometimes', 'array'],
            'filter_user_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'period_from.required' => 'Chưa chọn ngày bắt đầu kỳ.',
            'period_to.required' => 'Chưa chọn ngày kết thúc kỳ.',
            'period_to.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
        ];
    }
}
