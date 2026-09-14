<?php

namespace Modules\Dashboard\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'months_before' => ['nullable', 'integer', 'min:0', 'max:24'],
            'months_after' => ['nullable', 'integer', 'min:0', 'max:24'],
        ];
    }
}
