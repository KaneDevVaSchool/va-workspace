<?php

namespace Modules\Evaluation\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportEvaluationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q'      => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    /** @return array{q: string, status: string} */
    public function filters(): array
    {
        $data = $this->validated();

        return [
            'q'      => trim((string) ($data['q'] ?? '')),
            'status' => trim((string) ($data['status'] ?? '')),
        ];
    }
}
