<?php

namespace Modules\WorkspaceConfig\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignWorkspaceConfigRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route đã bọc middleware auth + permission:workspace_config.assign_role_department
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_code' => ['required', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Vui lòng chọn thành viên.',
            'role_code.required' => 'Vui lòng chọn vai trò.',
        ];
    }
}
