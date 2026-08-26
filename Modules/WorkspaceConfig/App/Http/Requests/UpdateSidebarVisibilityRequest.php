<?php

namespace Modules\WorkspaceConfig\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\WorkspaceConfig\App\Services\DepartmentSidebarConfigService;

class UpdateSidebarVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route đã bọc middleware auth + permission:workspace_config.manage_sidebar_department
    }

    public function rules(): array
    {
        return [
            'menu_key' => ['required', 'string'],
            'is_visible' => ['sometimes', 'boolean'],
            'custom_label' => ['sometimes', 'nullable', 'string', 'max:'.DepartmentSidebarConfigService::LABEL_MAX_LENGTH],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->exists('is_visible') && ! $this->exists('custom_label')) {
                $validator->errors()->add('menu_key', 'Thiếu dữ liệu cập nhật.');
            }
        });
    }
}
