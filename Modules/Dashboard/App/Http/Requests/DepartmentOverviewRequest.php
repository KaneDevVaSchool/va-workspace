<?php

namespace Modules\Dashboard\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DepartmentOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Phạm vi phòng ban tự chặn trong Controller/Service.
    }

    public function rules(): array
    {
        return [
            'department_id' => ['nullable', 'integer', 'min:1'],
            'team_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
