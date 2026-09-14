<?php

namespace Modules\Dashboard\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Project\App\Enums\TaskEnums;

class DepartmentEmployeesListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['nullable', 'integer', 'min:1'],
            'team_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:'.implode(',', TaskEnums::STATUSES)],
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
