<?php

namespace Modules\Identity\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivityLogExportRequest extends FormRequest
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
            'actor_id' => ['nullable', 'string', 'max:32', 'required_if:export_kind,user'],
            'date_from' => ['nullable', 'date', 'required_if:export_kind,date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from', 'required_if:export_kind,date'],
            'ip' => ['nullable', 'string', 'max:45'],
            'subject_type' => ['nullable', 'string', 'max:64'],
            'export_kind' => ['nullable', 'string', Rule::in(['filter', 'date', 'user'])],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.required_if' => 'Vui lòng chọn ngày bắt đầu.',
            'date_to.required_if' => 'Vui lòng chọn ngày kết thúc.',
            'date_to.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            'actor_id.required_if' => 'Vui lòng chọn người dùng cần xuất.',
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

    public function exportKind(): string
    {
        return (string) ($this->validated()['export_kind'] ?? 'filter');
    }
}
