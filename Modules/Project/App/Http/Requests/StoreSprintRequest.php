<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Project\App\Models\Sprint;

class StoreSprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm tra qua middleware route ('permission:task.create')
    }

    public function rules(): array
    {
        return [
            'phase_id' => [
                'required',
                'integer',
                Rule::exists('tasks', 'id')
                    ->where('type', 'phase')
                    ->where('project_id', $this->route('project')?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', Rule::in(Sprint::STATUSES)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'phase_id.required' => 'Vui lòng chọn giai đoạn cho sprint.',
            'phase_id.exists' => 'Giai đoạn không hợp lệ.',
            'name.required' => 'Vui lòng nhập tên sprint.',
            'name.max' => 'Tên sprint không được vượt quá 255 ký tự.',
            'status.in' => 'Trạng thái sprint không hợp lệ.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
        ];
    }
}
