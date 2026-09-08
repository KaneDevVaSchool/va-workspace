<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Đồng bộ danh mục (category) hoặc phase của 1 dự án trong 1 transaction:
 * tạo mới / sửa / đổi thứ tự / xoá (chặn nếu còn con).
 */
class SyncProjectStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = (string) $this->route('type');
        $isPhase = $type === 'phase';

        return [
            'items' => ['present', 'array', 'max:100'],
            'items.*.id' => ['nullable', 'integer', 'exists:tasks,id'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:5000'],
            'items.*.progress_type' => [
                'nullable',
                'string',
                Rule::in(['average', 'duration_weighted', 'task_weighted']),
            ],
            'items.*.start_date' => [$isPhase ? 'nullable' : 'prohibited', 'date'],
            'items.*.end_date' => [$isPhase ? 'nullable' : 'prohibited', 'date'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'deleted_ids' => ['nullable', 'array'],
            'deleted_ids.*' => ['integer', 'distinct', 'exists:tasks,id'],
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
            'items.*.title.required' => 'Tên không được để trống.',
            'items.*.title.max' => 'Tên không được vượt quá 255 ký tự.',
            'items.*.id.exists' => 'Mục cần sửa không tồn tại.',
            'items.*.progress_type.in' => 'Cách tính tiến độ không hợp lệ.',
            'items.*.start_date.prohibited' => 'Danh mục không dùng ngày bắt đầu/kết thúc.',
            'items.*.end_date.prohibited' => 'Danh mục không dùng ngày bắt đầu/kết thúc.',
            'deleted_ids.*.exists' => 'Mục cần xoá không tồn tại.',
        ];
    }
}
