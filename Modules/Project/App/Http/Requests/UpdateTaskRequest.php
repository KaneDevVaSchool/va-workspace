<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Project\App\Enums\TaskEnums;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm tra qua middleware route ('permission:task.create')
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:tasks,id'],
            'type' => ['sometimes', 'string', 'in:'.implode(',', TaskEnums::TYPES)],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', TaskEnums::STATUSES)],
            'priority' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', TaskEnums::PRIORITIES)],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'actual_start_date' => ['sometimes', 'nullable', 'date'],
            'actual_end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:actual_start_date'],
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'progress_percent' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'parent_id.exists' => 'Công việc cha không tồn tại.',
            'type.in' => 'Loại công việc không hợp lệ.',
            'title.max' => 'Tên công việc không được vượt quá 255 ký tự.',
            'description.max' => 'Mô tả không được vượt quá 5000 ký tự.',
            'status.in' => 'Trạng thái công việc không hợp lệ.',
            'priority.in' => 'Mức độ ưu tiên không hợp lệ.',
            'start_date.date' => 'Ngày bắt đầu không hợp lệ.',
            'end_date.date' => 'Ngày kết thúc không hợp lệ.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            'actual_start_date.date' => 'Ngày bắt đầu thực tế không hợp lệ.',
            'actual_end_date.date' => 'Ngày kết thúc thực tế không hợp lệ.',
            'actual_end_date.after_or_equal' => 'Ngày kết thúc thực tế phải sau hoặc bằng ngày bắt đầu thực tế.',
            'assignee_id.exists' => 'Người thực hiện không tồn tại.',
            'progress_percent.min' => 'Tiến độ tối thiểu là 0%.',
            'progress_percent.max' => 'Tiến độ tối đa là 100%.',
        ];
    }
}
