<?php

namespace Modules\WorkspaceConfig\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceConfigTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route đã bọc middleware auth + permission:team.manage
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'team_lead_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên nhóm.',
        ];
    }
}
