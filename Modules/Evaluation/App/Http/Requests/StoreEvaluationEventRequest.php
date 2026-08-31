<?php

namespace Modules\Evaluation\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvaluationEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'criterion_id' => ['required', 'integer'],
            'level_code' => ['required', 'string', 'max:8'],
            'occurred_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
            'evidence_path' => ['nullable', 'string', 'max:255'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            // Kỳ đang xem trên bảng tổng hợp. Không bắt buộc — gửi kèm thì
            // phản hồi có luôn dòng số liệu đã tính lại để bảng cập nhật tại
            // chỗ; không gửi thì phản hồi như cũ.
            'period_from' => ['nullable', 'date_format:Y-m-d', 'required_with:period_to'],
            'period_to' => ['nullable', 'date_format:Y-m-d', 'required_with:period_from', 'after_or_equal:period_from'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Chưa chọn nhân sự được ghi nhận.',
            'user_id.exists' => 'Nhân sự không tồn tại.',
            'criterion_id.required' => 'Chưa chọn tiêu chí.',
            'level_code.required' => 'Chưa chọn mức điểm.',
            'occurred_at.required' => 'Chưa chọn ngày phát sinh.',
            'occurred_at.date' => 'Ngày phát sinh không hợp lệ.',
            'reason.max' => 'Lý do không được quá 500 ký tự.',
            'task_id.exists' => 'Công việc liên quan không tồn tại.',
        ];
    }
}
