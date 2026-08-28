<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk actions (PR7) — chỉ whitelist manager_id/weight (đúng quyết định
 * đã chốt: không cho bulk sửa status/title/assignee... tránh sai sót hàng
 * loạt ngoài ý muốn).
 */
class BulkUpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm tra qua middleware route ('permission:task.create')
    }

    public function rules(): array
    {
        return [
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['required', 'integer'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'task_ids.required' => 'Chọn ít nhất một công việc.',
            'task_ids.min' => 'Chọn ít nhất một công việc.',
            'manager_id.exists' => 'Người quản lý không tồn tại.',
            'weight.min' => 'Tỷ trọng tối thiểu là 0%.',
            'weight.max' => 'Tỷ trọng tối đa là 100%.',
        ];
    }

    /** Phải có ít nhất 1 trong 2 field để cập nhật hàng loạt (kể cả weight=0). */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasManager = $this->has('manager_id') && $this->input('manager_id') !== null && $this->input('manager_id') !== '';
            $hasWeight = $this->has('weight') && $this->input('weight') !== null && $this->input('weight') !== '';
            if (! $hasManager && ! $hasWeight) {
                $validator->errors()->add('manager_id', 'Chọn ít nhất một trường để cập nhật hàng loạt.');
            }
        });
    }
}
