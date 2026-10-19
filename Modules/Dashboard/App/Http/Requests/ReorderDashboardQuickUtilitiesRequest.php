<?php

namespace Modules\Dashboard\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderDashboardQuickUtilitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.utility_key' => ['required', 'string', 'max:64'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
