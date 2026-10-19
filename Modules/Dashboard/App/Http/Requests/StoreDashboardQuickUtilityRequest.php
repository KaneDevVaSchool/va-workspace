<?php

namespace Modules\Dashboard\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Dashboard\App\Services\DashboardQuickUtilityService;

class StoreDashboardQuickUtilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'utility_key' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'label' => ['required', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:64'],
            'tone' => ['nullable', 'string', Rule::in(DashboardQuickUtilityService::TONES)],
            'link_type' => ['nullable', 'string', Rule::in(DashboardQuickUtilityService::LINK_TYPES)],
            'link_target' => ['nullable', 'string', 'max:512'],
            'is_visible' => ['nullable', 'boolean'],
        ];
    }
}
