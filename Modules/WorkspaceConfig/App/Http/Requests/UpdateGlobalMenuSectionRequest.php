<?php

namespace Modules\WorkspaceConfig\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGlobalMenuSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section_key' => ['required', 'string', 'max:80'],
            'custom_label' => ['nullable', 'string', 'max:120'],
        ];
    }
}
