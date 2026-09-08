<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Project\App\Enums\TaskEnums;

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
            'items.*.start_date' => ['nullable', 'date'],
            'items.*.end_date' => ['nullable', 'date'],
            'items.*.assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'items.*.progress_type' => ['nullable', 'string', 'in:'.implode(',', TaskEnums::PROGRESS_TYPES)],
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
            'items.*.assignee_id.exists' => 'Người thực hiện không tồn tại.',
        ];
    }
}
