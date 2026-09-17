<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Project\App\Models\ProjectTestCase;

class UpdateProjectTestCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm tra qua middleware route ('permission:test_case.manage')
    }

    public function rules(): array
    {
        // Route chỉ có {testCase} (int) — lấy project_id qua chính testcase
        // để ràng buộc phase_id đúng dự án (không cho gán giai đoạn của dự
        // án khác).
        $projectId = ProjectTestCase::query()->find($this->route('testCase'))?->project_id;

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'steps' => ['nullable', 'string', 'max:5000'],
            'expected_result' => ['nullable', 'string', 'max:5000'],
            'actual_result' => ['nullable', 'string', 'max:5000'],
            'link_url' => ['nullable', 'url', 'max:2048'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'phase_id' => [
                'nullable',
                'integer',
                Rule::exists('tasks', 'id')->where('type', 'phase')->where('project_id', $projectId),
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
