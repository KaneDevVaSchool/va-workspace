<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Project\App\Enums\ProjectEnums;
use Modules\Project\App\Services\ProjectService;

/**
 * Bước 2 của luồng nhập Excel — nhận JSON các dòng đã qua bước preview,
 * KHÔNG nhận lại file. Shape khớp với `data` trong ProjectExcelImporter::preview().
 */
class ConfirmImportProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && app(ProjectService::class)->userCanCreate($this->user());
    }

    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.action' => ['nullable', 'string', Rule::in(['create', 'update'])],
            'rows.*.project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'rows.*.provided_fields' => ['nullable', 'array'],
            'rows.*.provided_fields.*' => ['string'],
            // name/type: bắt buộc khi tạo mới, tuỳ chọn khi cập nhật (giữ nguyên
            // giá trị cũ nếu Excel để trống) — kiểm tra theo cặp action/field
            // bằng closure vì không thể vừa required vừa nullable trên 1 rule tĩnh.
            'rows.*.name' => [
                function (string $attribute, mixed $value, \Closure $fail) {
                    $index = $this->rowIndex($attribute);
                    $action = $this->input("rows.{$index}.action", 'create');
                    if ($action === 'create' && trim((string) $value) === '') {
                        $fail('Thiếu tên dự án.');
                    }
                },
                'nullable', 'string', 'max:255',
            ],
            'rows.*.type' => [
                function (string $attribute, mixed $value, \Closure $fail) {
                    $index = $this->rowIndex($attribute);
                    $action = $this->input("rows.{$index}.action", 'create');
                    if ($action === 'create' && trim((string) $value) === '') {
                        $fail('Thiếu loại dự án.');
                    }
                },
                'nullable', 'string', 'max:100',
            ],
            'rows.*.lead_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'rows.*.lead_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'rows.*.executing_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'rows.*.executing_department_ids' => ['nullable', 'array'],
            'rows.*.executing_department_ids.*' => ['integer', 'exists:departments,id'],
            'rows.*.member_ids' => ['nullable', 'array'],
            'rows.*.member_ids.*' => ['integer', 'exists:users,id'],
            'rows.*.follower_ids' => ['nullable', 'array'],
            'rows.*.follower_ids.*' => ['integer', 'exists:users,id'],
            'rows.*.label_ids' => ['nullable', 'array'],
            'rows.*.label_ids.*' => ['integer', 'exists:project_labels,id'],
            'rows.*.status' => ['nullable', 'string', Rule::in(ProjectEnums::STATUSES)],
            'rows.*.importance' => ['nullable', 'string', Rule::in(ProjectEnums::IMPORTANCE_LEVELS)],
            'rows.*.start_date' => ['nullable', 'date'],
            'rows.*.end_date' => ['nullable', 'date'],
            'rows.*.progress_method' => ['nullable', 'string', Rule::in(ProjectEnums::PROGRESS_METHODS)],
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

    /** Suy ra chỉ số dòng trong mảng rows từ tên attribute "rows.<i>.name". */
    private function rowIndex(string $attribute): string
    {
        return explode('.', $attribute)[1] ?? '0';
    }
}
