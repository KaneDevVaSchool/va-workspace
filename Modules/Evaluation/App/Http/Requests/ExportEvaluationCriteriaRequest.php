<?php

namespace Modules\Evaluation\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportEvaluationCriteriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:255'],
            'kind' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', 'string', Rule::in(['scale', 'behavior'])],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    /** @return array{q: string, kind: string, type: string, status: string} */
    public function filters(): array
    {
        $data = $this->validated();

        return [
            'q' => trim((string) ($data['q'] ?? '')),
            'kind' => trim((string) ($data['kind'] ?? '')),
            'type' => trim((string) ($data['type'] ?? '')),
            'status' => trim((string) ($data['status'] ?? '')),
        ];
    }
}
