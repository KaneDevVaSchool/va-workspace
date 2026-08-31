<?php

namespace Modules\Evaluation\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Kỳ tổng hợp đánh giá.
 *
 * Chỉ nhận khoảng ngày đã quy đổi sẵn — giao diện tự đổi "tháng 8" thành
 * 01/08–31/08 rồi mới gọi, nên máy chủ không cần biết người dùng đang xem
 * theo tháng, tuần hay ngày.
 */
class EvaluationSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Quyền theo phòng ban kiểm tra trong Controller (giống
        // EvaluationEventController) vì cần biết department_id của người dùng.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->input('from');
            $to = $this->input('to');

            if (! is_string($from) || ! is_string($to)) {
                return;
            }

            // Chặn kỳ quá dài: bảng tổng hợp tải toàn bộ công việc của cả
            // phòng ban trong kỳ, kỳ vài năm sẽ nặng vô ích trong khi nghiệp
            // vụ chỉ chấm theo tháng.
            $days = strtotime($to) - strtotime($from);
            if ($days > 366 * 86400) {
                $validator->errors()->add('to', 'Kỳ tổng hợp không được dài quá 366 ngày.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'from.required' => 'Chưa chọn ngày bắt đầu kỳ.',
            'from.date_format' => 'Ngày bắt đầu kỳ không hợp lệ.',
            'to.required' => 'Chưa chọn ngày kết thúc kỳ.',
            'to.date_format' => 'Ngày kết thúc kỳ không hợp lệ.',
            'to.after_or_equal' => 'Ngày kết thúc kỳ phải từ ngày bắt đầu trở đi.',
        ];
    }
}
