<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Chỉ cho chuyển giao cho người ĐANG làm việc. Trước đây chỉ kiểm
            // exists:users,id nên giao được cho người đã nghỉ việc (status khác
            // 'active') — công việc rơi vào tài khoản không ai dùng.
            //
            // CHƯA siết phạm vi theo phòng ban/dự án: hiện người có quyền
            // task.delegate vẫn giao được cho bất kỳ ai đang hoạt động trong hệ
            // thống. Cần chốt phạm vi hợp lệ trước khi siết — xem
            // docs/known-issues.md và plans/2026-09-30-master-plan-trien-khai.md §6.5.
            'delegated_to_employee_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('status', 'active'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'task_ids.required' => 'Chọn ít nhất một công việc.',
            'task_ids.min' => 'Chọn ít nhất một công việc.',
            'delegated_to_employee_id.required' => 'Chọn người tiếp nhận.',
            'delegated_to_employee_id.exists' => 'Người tiếp nhận không tồn tại hoặc đã nghỉ việc.',
        ];
    }
}
