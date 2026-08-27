<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Project\App\Services\ProjectExcelExporter;

class ExportProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'tab' => ['nullable', 'string', Rule::in([
                'all',
                'in_progress',
                'completed',
                'on_hold',
                'planning',
                'cancelled',
                'following',
                'my_tasks',
                'my_department',
            ])],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer', 'exists:project_labels,id'],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', Rule::in(array_keys(ProjectExcelExporter::COLUMNS))],
        ];
    }

    /** null = không giới hạn cột (xuất đủ, dùng làm file mẫu nhập lại). */
    public function columns(): ?array
    {
        $columns = $this->validated()['columns'] ?? null;
        if (! is_array($columns) || $columns === []) {
            return null;
        }

        return array_values($columns);
    }

    protected function prepareForValidation(): void
    {
        $ids = $this->input('label_ids');
        if ($ids === null || $ids === '') {
            return;
        }
        if (! is_array($ids)) {
            $this->merge(['label_ids' => [$ids]]);
        }
    }

    /** @return array{q: string, tab: string, label_ids: list<int>} */
    public function filters(): array
    {
        $data = $this->validated();
        $labelIds = $data['label_ids'] ?? [];
        if (! is_array($labelIds)) {
            $labelIds = $labelIds === null || $labelIds === '' ? [] : [$labelIds];
        }

        return [
            'q' => trim((string) ($data['q'] ?? '')),
            'tab' => trim((string) ($data['tab'] ?? 'all')),
            'label_ids' => array_values(array_filter(array_map('intval', $labelIds))),
        ];
    }
}
