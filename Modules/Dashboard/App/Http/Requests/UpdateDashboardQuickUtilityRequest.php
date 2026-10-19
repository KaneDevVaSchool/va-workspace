<?php

namespace Modules\Dashboard\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Dashboard\App\Services\DashboardQuickUtilityService;

class UpdateDashboardQuickUtilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'required', 'string', 'max:120'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:64'],
            'tone' => ['sometimes', 'string', Rule::in(DashboardQuickUtilityService::TONES)],
            'link_type' => ['sometimes', 'string', Rule::in(DashboardQuickUtilityService::LINK_TYPES)],
            'link_target' => ['sometimes', 'nullable', 'string', 'max:512'],
            'is_visible' => ['sometimes', 'boolean'],
        ];
    }
}
