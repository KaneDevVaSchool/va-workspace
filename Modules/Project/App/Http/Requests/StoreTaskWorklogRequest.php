<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskWorklogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm tra qua middleware route ('permission:task.create')
    }

    public function rules(): array
    {
        return [
            'work_date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'work_date.required' => 'Chọn ngày làm việc.',
            'work_date.date' => 'Ngày làm việc không hợp lệ.',
            'hours.required' => 'Nhập số giờ làm.',
            'hours.min' => 'Số giờ tối thiểu là 0.25.',
            'hours.max' => 'Số giờ tối đa trong 1 ngày là 24.',
            'note.max' => 'Ghi chú không được vượt quá 2000 ký tự.',
        ];
    }
}
