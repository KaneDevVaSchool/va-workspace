<?php

namespace Modules\Evaluation\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEvaluationEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'criterion_id' => ['sometimes', 'integer'],
            'level_code' => ['sometimes', 'string', 'max:8'],
            'occurred_at' => ['sometimes', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
            'evidence_path' => ['nullable', 'string', 'max:255'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.exists' => 'Nhân sự không tồn tại.',
            'occurred_at.date' => 'Ngày phát sinh không hợp lệ.',
            'reason.max' => 'Lý do không được quá 500 ký tự.',
            'task_id.exists' => 'Công việc liên quan không tồn tại.',
        ];
    }
}
