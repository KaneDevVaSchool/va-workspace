<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Project\App\Enums\ProjectEnums;

class UpdateProjectSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code_pattern' => [
                'required',
                'string',
                'max:100',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! preg_match('/\{count(?::\d+)?\}/', $value)) {
                        $fail('Mẫu mã dự án phải chứa {count} hoặc {count:N}.');
                    }
                },
            ],
            'code_counter' => ['required', 'integer', 'min:0'],
            'default_progress_method' => ['required', 'string', 'in:'.implode(',', ProjectEnums::PROGRESS_METHODS)],
            'auto_start_on_begin_date' => ['required', 'boolean'],
            'shift_task_dates_with_project' => ['required', 'boolean'],
            'hide_cross_tasks_from_assignees' => ['required', 'boolean'],
            'hide_child_tasks_from_followers' => ['required', 'boolean'],
            'constrain_task_dates_to_project' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code_pattern.required' => 'Mẫu mã dự án không được để trống.',
            'code_pattern.max' => 'Mẫu mã dự án không được vượt quá 100 ký tự.',
            'code_counter.required' => 'Bộ đếm không được để trống.',
            'code_counter.integer' => 'Bộ đếm phải là số nguyên.',
            'code_counter.min' => 'Bộ đếm không được âm.',
            'default_progress_method.required' => 'Phương pháp tính tiến độ không được để trống.',
            'default_progress_method.in' => 'Phương pháp tính tiến độ không hợp lệ.',
        ];
    }
}
