<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectTestCaseCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // đúng người (assignee/người tạo) kiểm tra trong ProjectTestCaseService::canCheck1()/canCheck2()
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['pending', 'passed', 'failed'])],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Vui lòng chọn kết quả kiểm tra.',
            'status.in' => 'Kết quả kiểm tra không hợp lệ.',
        ];
    }
}
