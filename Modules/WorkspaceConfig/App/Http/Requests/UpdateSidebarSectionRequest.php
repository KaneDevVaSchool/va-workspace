<?php

namespace Modules\WorkspaceConfig\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\WorkspaceConfig\App\Services\DepartmentSidebarConfigService;

class UpdateSidebarSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section_key' => ['required', 'string', Rule::in(array_keys(DepartmentSidebarConfigService::SECTIONS))],
            'custom_label' => ['present', 'nullable', 'string', 'max:'.DepartmentSidebarConfigService::LABEL_MAX_LENGTH],
        ];
    }
}
