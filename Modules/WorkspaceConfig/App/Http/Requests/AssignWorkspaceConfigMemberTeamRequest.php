<?php

namespace Modules\WorkspaceConfig\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignWorkspaceConfigMemberTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route đã bọc middleware auth + permission:team.manage
    }

    public function rules(): array
    {
        return [
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
        ];
    }
}
