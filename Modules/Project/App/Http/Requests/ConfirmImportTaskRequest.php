<?php

namespace Modules\Project\App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Project\App\Enums\TaskEnums;
use Modules\Project\App\Services\TaskImportanceOptions;

/**
 * Bước 2 của luồng nhập Excel — nhận JSON các dòng đã qua bước preview,
 * KHÔNG nhận lại file. Frontend spread row.data lên top-level trước khi
 * gửi (cùng pattern ConfirmImportProjectRequest/ProjectList.vue), shape
 * khớp với `data` trong TaskExcelImporter::resolveRow() + action/
 * provided_fields/task_id/row.
 */
class ConfirmImportTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm tra qua middleware route ('permission:task.create')
    }

    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.action' => ['nullable', 'string', Rule::in(['create', 'update'])],
            'rows.*.task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'rows.*.project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'rows.*.provided_fields' => ['nullable', 'array'],
            'rows.*.provided_fields.*' => ['string'],
            // title/project_id: bắt buộc khi tạo mới, tuỳ chọn khi cập nhật
            // (giữ nguyên giá trị cũ nếu Excel để trống) — kiểm tra theo cặp
            // action/field bằng closure, cùng pattern ConfirmImportProjectRequest.
            'rows.*.title' => [
                function (string $attribute, mixed $value, \Closure $fail) {
                    $index = $this->rowIndex($attribute);
                    $action = $this->input("rows.{$index}.action", 'create');
                    if ($action === 'create' && trim((string) $value) === '') {
                        $fail('Thiếu tên công việc.');
                    }
                },
                'nullable', 'string', 'max:255',
            ],
            'rows.*.type' => ['nullable', 'string', Rule::in(TaskEnums::TYPES)],
            'rows.*.status' => ['nullable', 'string', Rule::in(TaskEnums::STATUSES)],
            'rows.*.priority' => [
                'nullable', 'string', 'max:50',
                function (string $attribute, mixed $value, Closure $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $index = $this->rowIndex($attribute);
                    $projectId = $this->input("rows.{$index}.project_id");
                    $accepted = app(TaskImportanceOptions::class)->acceptedValuesForContext(
                        $projectId !== null && $projectId !== '' ? (int) $projectId : null,
                        $this->user()?->department_id,
                    );
                    if (! TaskEnums::isAcceptedValue((string) $value, $accepted)) {
                        $fail('Mức độ ưu tiên không hợp lệ.');
                    }
                },
            ],
            'rows.*.assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'rows.*.manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'rows.*.start_date' => ['nullable', 'date'],
            'rows.*.end_date' => ['nullable', 'date'],
            'rows.*.progress_type' => ['nullable', 'string', Rule::in(TaskEnums::PROGRESS_TYPES)],
            'rows.*.progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'rows.*.progress_number' => ['nullable', 'numeric', 'min:0'],
            'rows.*.progress_total' => ['nullable', 'numeric', 'min:0'],
            'rows.*.unit' => ['nullable', 'string', 'max:50'],
            'rows.*.estimated_hours' => ['nullable', 'numeric', 'min:0'],
            'rows.*.weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rows.*.description' => ['nullable', 'string', 'max:5000'],
            'rows.*.row' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'rows.required' => 'Không có dòng nào để nhập.',
            'rows.min' => 'Không có dòng nào để nhập.',
        ];
    }

    /** Suy ra chỉ số dòng trong mảng rows từ tên attribute "rows.<i>.title". */
    private function rowIndex(string $attribute): string
    {
        return explode('.', $attribute)[1] ?? '0';
    }
}
