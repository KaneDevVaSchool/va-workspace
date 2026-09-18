<?php

namespace Modules\Project\App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Project\App\Enums\TaskEnums;
use Modules\Project\App\Models\Sprint;
use Modules\Project\App\Services\TaskImportanceOptions;

/**
 * Tạo nhiều công việc trong 1 transaction — mỗi dòng có field riêng
 * (khác titles[] dùng chung payload).
 */
class StoreBulkTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:5000'],
            'items.*.parent_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'items.*.sprint_id' => ['nullable', 'integer', $this->sprintRule()],
            // project_id: chỉ dùng khi tạo từ trang "Tất cả công việc" (không có
            // {project} cố định trên route) — mỗi dòng tự chọn dự án, để trống =
            // việc thường xuyên. Route /{project}/tasks/bulk bỏ qua field này.
            'items.*.project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'items.*.start_date' => ['nullable', 'date'],
            'items.*.end_date' => ['nullable', 'date'],
            'items.*.start_time' => ['nullable', 'date_format:H:i'],
            'items.*.due_time' => ['nullable', 'date_format:H:i'],
            'items.*.estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'items.*.assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'items.*.progress_type' => ['nullable', 'string', 'in:'.implode(',', TaskEnums::PROGRESS_TYPES)],
            'items.*.priority' => ['nullable', 'string', 'max:50', $this->priorityRule()],
            'items.*.weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.type' => ['nullable', 'string', Rule::in(['task'])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('items', []) as $index => $item) {
                $start = $item['start_date'] ?? null;
                $end = $item['end_date'] ?? null;
                if ($start && $end && $end < $start) {
                    $validator->errors()->add(
                        "items.{$index}.end_date",
                        'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Nhập ít nhất một công việc.',
            'items.min' => 'Nhập ít nhất một công việc.',
            'items.max' => 'Mỗi lần tối đa 100 công việc.',
            'items.*.title.required' => 'Tên công việc không được để trống.',
            'items.*.title.max' => 'Tên công việc không được vượt quá 255 ký tự.',
            'items.*.parent_id.exists' => 'Công việc cha không tồn tại.',
            'items.*.sprint_id.integer' => 'Sprint không hợp lệ.',
            'items.*.project_id.exists' => 'Dự án không tồn tại.',
            'items.*.assignee_id.exists' => 'Người thực hiện không tồn tại.',
            'items.*.start_time.date_format' => 'Giờ bắt đầu không hợp lệ (định dạng HH:MM).',
            'items.*.due_time.date_format' => 'Giờ hạn không hợp lệ (định dạng HH:MM).',
            'items.*.estimated_hours.min' => 'Thời gian dự kiến không được âm.',
            'items.*.priority.max' => 'Phân loại độ khó không hợp lệ.',
            'items.*.weight.min' => 'Tỷ trọng tối thiểu là 0%.',
            'items.*.weight.max' => 'Tỷ trọng tối đa là 100%.',
        ];
    }

    private function priorityRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if ($value === null || $value === '') {
                return;
            }

            $index = explode('.', $attribute)[1] ?? null;
            $routeProject = $this->route('project');
            $projectId = $routeProject !== null
                ? (int) (is_object($routeProject) ? $routeProject->id : $routeProject)
                : (int) ($this->input("items.{$index}.project_id") ?: 0);

            $accepted = app(TaskImportanceOptions::class)->acceptedValuesForContext(
                $projectId > 0 ? $projectId : null,
                $this->user()?->department_id,
            );

            if (! TaskEnums::isAcceptedValue((string) $value, $accepted)) {
                $fail('Phân loại độ khó không hợp lệ.');
            }
        };
    }

    private function sprintRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if ($value === null || $value === '') {
                return;
            }

            $index = explode('.', $attribute)[1] ?? null;
            $routeProject = $this->route('project');
            $projectId = $routeProject !== null
                ? (int) (is_object($routeProject) ? $routeProject->id : $routeProject)
                : (int) ($this->input("items.{$index}.project_id") ?: 0);

            if ($projectId <= 0) {
                $fail('Sprint chỉ dùng cho công việc thuộc dự án.');

                return;
            }

            if (! Sprint::query()->where('id', $value)->where('project_id', $projectId)->exists()) {
                $fail('Sprint không thuộc dự án này.');
            }
        };
    }
}
