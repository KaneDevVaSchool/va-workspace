<?php

namespace Modules\Project\App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Project\App\Enums\TaskEnums;
use Modules\Project\App\Services\TaskImportanceOptions;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm tra qua middleware route ('permission:task.create')
    }

    public function rules(): array
    {
        // Route dùng implicit model binding (Task $task) đúng cho update —
        // đọc progress_type HIỆN CÓ trên record khi client chỉ PUT một phần
        // field (PATCH-semantics) mà không gửi lại progress_type, để ràng
        // buộc progress_number/progress_total/unit vẫn đúng ngữ cảnh.
        $currentType = $this->route('task')?->progress_type ?? 'percent';
        $isQuantity = ($this->input('progress_type') ?? $currentType) === 'quantity';

        return [
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:tasks,id'],
            'type' => ['sometimes', 'string', 'in:'.implode(',', TaskEnums::TYPES)],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', TaskEnums::STATUSES)],
            'priority' => ['sometimes', 'nullable', 'string', 'max:50', $this->priorityRule()],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'start_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'due_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'actual_start_date' => ['sometimes', 'nullable', 'date'],
            'actual_end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:actual_start_date'],
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'progress_percent' => [
                'sometimes', 'nullable', 'integer', 'min:0', 'max:100',
                Rule::prohibitedIf($isQuantity),
            ],
            'progress_type' => ['sometimes', 'string', 'in:'.implode(',', TaskEnums::PROGRESS_TYPES)],
            'progress_number' => [
                'sometimes', 'nullable', 'numeric', 'min:0', 'lte:progress_total',
                Rule::requiredIf($isQuantity),
                Rule::prohibitedIf(! $isQuantity),
            ],
            'progress_total' => [
                'sometimes', 'nullable', 'numeric', 'gt:0',
                Rule::requiredIf($isQuantity),
                Rule::prohibitedIf(! $isQuantity),
            ],
            'unit' => [
                'sometimes', 'nullable', 'string', 'max:50',
                Rule::prohibitedIf(! $isQuantity),
            ],
            'estimated_hours' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999.99'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
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
            'start_time.date_format' => 'Giờ bắt đầu không hợp lệ (định dạng HH:MM).',
            'end_date.date' => 'Ngày kết thúc không hợp lệ.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            'due_time.date_format' => 'Giờ hạn không hợp lệ (định dạng HH:MM).',
            'actual_start_date.date' => 'Ngày bắt đầu thực tế không hợp lệ.',
            'actual_end_date.date' => 'Ngày kết thúc thực tế không hợp lệ.',
            'actual_end_date.after_or_equal' => 'Ngày kết thúc thực tế phải sau hoặc bằng ngày bắt đầu thực tế.',
            'assignee_id.exists' => 'Người thực hiện không tồn tại.',
            'manager_id.exists' => 'Người quản lý không tồn tại.',
            'progress_percent.min' => 'Tiến độ tối thiểu là 0%.',
            'progress_percent.max' => 'Tiến độ tối đa là 100%.',
            'progress_percent.prohibited' => 'Không thể nhập tay tiến độ khi tính theo khối lượng — hệ thống tự tính.',
            'progress_type.in' => 'Cách tính tiến độ không hợp lệ.',
            'progress_number.required' => 'Nhập khối lượng đã hoàn thành.',
            'progress_number.prohibited' => 'Chỉ nhập khối lượng khi tính tiến độ theo khối lượng.',
            'progress_number.lte' => 'Khối lượng hoàn thành không được vượt quá khối lượng cần hoàn thành.',
            'progress_total.required' => 'Nhập khối lượng cần hoàn thành.',
            'progress_total.prohibited' => 'Chỉ nhập khối lượng khi tính tiến độ theo khối lượng.',
            'progress_total.gt' => 'Khối lượng cần hoàn thành phải lớn hơn 0.',
            'unit.prohibited' => 'Chỉ nhập đơn vị khi tính tiến độ theo khối lượng.',
            'estimated_hours.min' => 'Thời gian dự kiến không được âm.',
            'weight.min' => 'Tỷ trọng tối thiểu là 0%.',
            'weight.max' => 'Tỷ trọng tối đa là 100%.',
        ];
    }

    private function priorityRule(): Closure
    {
        $task = $this->route('task');
        $accepted = app(TaskImportanceOptions::class)->acceptedValuesForContext(
            $task?->project_id,
            $this->user()?->department_id,
            $task?->origin_department_id,
        );

        return function (string $attribute, mixed $value, Closure $fail) use ($accepted) {
            if ($value === null || $value === '') {
                return;
            }
            if (! TaskEnums::isAcceptedValue((string) $value, $accepted)) {
                $fail('Mức độ ưu tiên không hợp lệ.');
            }
        };
    }
}
