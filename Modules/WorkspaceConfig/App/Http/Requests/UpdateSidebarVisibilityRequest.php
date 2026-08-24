<?php

namespace Modules\WorkspaceConfig\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'is_visible' => ['required', 'boolean'],
        ];
    }
}
