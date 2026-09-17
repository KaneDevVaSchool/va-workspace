<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectTestCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm tra qua middleware route ('permission:test_case.create')
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'steps' => ['nullable', 'string', 'max:5000'],
            'expected_result' => ['nullable', 'string', 'max:5000'],
            'actual_result' => ['nullable', 'string', 'max:5000'],
            'link_url' => ['nullable', 'url', 'max:2048'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'phase_id' => [
                'nullable',
                'integer',
                Rule::exists('tasks', 'id')
                    ->where('type', 'phase')
                    ->where('project_id', $this->route('project')?->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Vui lòng nhập tiêu đề testcase.',
            'title.max' => 'Tiêu đề không được vượt quá 255 ký tự.',
            'link_url.url' => 'Đường dẫn không hợp lệ.',
            'assignee_id.exists' => 'Người phụ trách không hợp lệ.',
            'phase_id.exists' => 'Giai đoạn không hợp lệ.',
        ];
    }
}
