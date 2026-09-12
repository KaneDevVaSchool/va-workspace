<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Project\App\Services\ProjectService;

class UpdateProjectTabConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm tra qua middleware route ('permission:project.update_department')
    }

    public function rules(): array
    {
        return [
            'disabled_tabs' => ['present', 'array'],
            'disabled_tabs.*' => [Rule::in(ProjectService::OPTIONAL_TABS)],
        ];
    }

    public function messages(): array
    {
        return [
            'disabled_tabs.*.in' => 'Tab không hợp lệ.',
        ];
    }
}
