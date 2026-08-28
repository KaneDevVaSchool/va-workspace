<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Project\App\Services\TaskExcelExporter;

class ExportTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm tra qua middleware route ('permission:task.view')
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'progress_type' => ['nullable', 'string'],
            'is_overdue' => ['nullable'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'tab' => ['nullable', 'string'],
            'sort_by' => ['nullable', 'string'],
            'sort_dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', Rule::in(array_keys(TaskExcelExporter::COLUMNS))],
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

    /** Tôn trọng đúng bộ lọc đang áp dụng trên trang danh sách (đối xứng currentFilterParams() phía frontend). */
    public function filters(): array
    {
        $data = $this->validated();

        return [
            'q' => $data['q'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'assignee_id' => $data['assignee_id'] ?? null,
            'manager_id' => $data['manager_id'] ?? null,
            'status' => $data['status'] ?? null,
            'type' => $data['type'] ?? null,
            'progress_type' => $data['progress_type'] ?? null,
            'is_overdue' => $data['is_overdue'] ?? null,
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
            'tab' => $data['tab'] ?? null,
            'sort_by' => $data['sort_by'] ?? null,
            'sort_dir' => $data['sort_dir'] ?? null,
        ];
    }
}
