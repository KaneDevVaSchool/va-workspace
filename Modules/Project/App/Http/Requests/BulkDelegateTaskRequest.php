<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDelegateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm tra qua middleware route ('permission:task.delegate')
    }

    public function rules(): array
    {
        return [
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['required', 'integer'],
            'delegated_to_employee_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'task_ids.required' => 'Chọn ít nhất một công việc.',
            'task_ids.min' => 'Chọn ít nhất một công việc.',
            'delegated_to_employee_id.required' => 'Chọn người tiếp nhận.',
            'delegated_to_employee_id.exists' => 'Người tiếp nhận không tồn tại.',
        ];
    }
}
