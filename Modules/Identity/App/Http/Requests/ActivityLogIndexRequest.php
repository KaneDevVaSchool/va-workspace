<?php

namespace Modules\Identity\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivityLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'string', 'max:64'],
            'actor_id' => ['nullable', 'string', 'max:32'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'ip' => ['nullable', 'string', 'max:45'],
            'subject_type' => ['nullable', 'string', 'max:64'],
            'per_page' => ['nullable', 'integer', Rule::in([20, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_to.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        $data = $this->validated();

        return [
            'q' => trim((string) ($data['q'] ?? '')),
            'action' => trim((string) ($data['action'] ?? '')),
            'actor_id' => trim((string) ($data['actor_id'] ?? '')),
            'date_from' => trim((string) ($data['date_from'] ?? '')),
            'date_to' => trim((string) ($data['date_to'] ?? '')),
            'ip' => trim((string) ($data['ip'] ?? '')),
            'subject_type' => trim((string) ($data['subject_type'] ?? '')),
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated()['per_page'] ?? 20);
    }
}
