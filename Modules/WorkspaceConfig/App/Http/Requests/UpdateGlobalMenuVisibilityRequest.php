<?php

namespace Modules\WorkspaceConfig\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\WorkspaceConfig\App\Services\GlobalMenuVisibilityService;

class UpdateGlobalMenuVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route đã bọc middleware permission:workspace_config.manage_global_menu.
        return true;
    }

    public function rules(): array
    {
        return [
            'menu_key' => ['required', 'string', Rule::in(array_keys(GlobalMenuVisibilityService::CATALOG))],
            'is_hidden' => ['sometimes', 'boolean'],
            'custom_label' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($v) {
            if (! $this->has('is_hidden') && ! $this->has('custom_label')) {
                $v->errors()->add('is_hidden', 'Phải gửi ít nhất is_hidden hoặc custom_label.');
            }
        });
    }
}
