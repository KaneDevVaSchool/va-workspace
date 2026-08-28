<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Project\App\Enums\TaskEnums;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm tra qua middleware route ('permission:task.create')
    }

    public function rules(): array
    {
        $needsTitle = ! $this->filled('titles');

        return [
            'parent_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'type' => ['nullable', 'string', 'in:'.implode(',', TaskEnums::TYPES)],
            'title' => [$needsTitle ? 'required' : 'nullable', 'string', 'max:255'],
            'titles' => ['nullable', 'array', 'min:1'],
            'titles.*' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', 'in:'.implode(',', TaskEnums::STATUSES)],
            'priority' => ['nullable', 'string', 'in:'.implode(',', TaskEnums::PRIORITIES)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'actual_start_date' => ['nullable', 'date'],
            'actual_end_date' => ['nullable', 'date', 'after_or_equal:actual_start_date'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'parent_id.exists' => 'Công việc cha không tồn tại.',
            'type.in' => 'Loại công việc không hợp lệ.',
            'title.required' => 'Tên công việc là bắt buộc.',
            'title.max' => 'Tên công việc không được vượt quá 255 ký tự.',
            'titles.min' => 'Nhập ít nhất một công việc.',
            'titles.*.required' => 'Tên công việc không được để trống.',
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
