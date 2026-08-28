<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Project\App\Enums\ProjectEnums;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware + Controller đã kiểm tra quyền.
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', 'string', 'max:100'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'lead_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'lead_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'executing_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'executing_department_ids' => ['nullable', 'array'],
            'executing_department_ids.*' => ['integer', 'exists:departments,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'actual_start_date' => ['nullable', 'date'],
            'actual_end_date' => ['nullable', 'date'],
            'progress_method' => ['sometimes', 'string', 'in:'.implode(',', ProjectEnums::PROGRESS_METHODS)],
            'status' => ['sometimes', 'string', 'in:'.implode(',', ProjectEnums::STATUSES)],
            'importance' => ['sometimes', 'string', 'in:'.implode(',', ProjectEnums::IMPORTANCE_LEVELS)],
            'description' => ['nullable', 'string', 'max:5000'],

            'shift_task_dates_with_project' => ['sometimes', 'boolean'],
            'hide_cross_tasks_from_assignees' => ['sometimes', 'boolean'],
            'hide_child_tasks_from_followers' => ['sometimes', 'boolean'],
            'constrain_task_dates_to_project' => ['sometimes', 'boolean'],

            'member_ids' => ['sometimes', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],

            'follower_ids' => ['sometimes', 'array'],
            'follower_ids.*' => ['integer', 'exists:users,id'],

            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => ['integer', 'exists:project_labels,id'],

            'scopes' => ['sometimes', 'array', 'max:1'],
            'scopes.*.scope_type' => ['required', 'string', 'in:'.implode(',', ProjectEnums::SCOPE_TYPES)],
            'scopes.*.department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'scopes.*.weight_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->exists('scopes')) {
                return;
            }

            $scopes = $this->input('scopes', []);
            if (! is_array($scopes) || $scopes === []) {
                return;
            }

            $row = $scopes[0] ?? [];
            if (($row['scope_type'] ?? null) === 'department' && empty($row['department_id'])) {
                $validator->errors()->add('scopes.0.department_id', 'Chọn phòng ban/bộ phận cho phạm vi triển khai.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Loại dự án là bắt buộc.',
            'type.max' => 'Tên loại dự án không được vượt quá 100 ký tự.',
            'name.required' => 'Tên dự án là bắt buộc.',
            'name.max' => 'Tên dự án không được vượt quá 255 ký tự.',
            'lead_user_id.exists' => 'Người phụ trách chính không tồn tại.',
            'lead_department_id.exists' => 'Phòng ban phụ trách không tồn tại.',
            'start_date.date' => 'Ngày bắt đầu không hợp lệ.',
            'end_date.date' => 'Ngày kết thúc không hợp lệ.',
            'actual_start_date.date' => 'Ngày bắt đầu thực tế không hợp lệ.',
            'actual_end_date.date' => 'Ngày kết thúc thực tế không hợp lệ.',
            'progress_method.in' => 'Phương pháp tính tiến độ không hợp lệ.',
            'status.in' => 'Trạng thái dự án không hợp lệ.',
            'importance.in' => 'Mức độ quan trọng không hợp lệ.',
            'member_ids.*.exists' => 'Có người thực hiện không tồn tại trong danh sách đã chọn.',
            'follower_ids.*.exists' => 'Có người theo dõi không tồn tại trong danh sách đã chọn.',
            'executing_department_id.exists' => 'Phòng ban thực hiện không tồn tại.',
            'executing_department_ids.*.exists' => 'Có phòng ban thực hiện không tồn tại trong danh sách đã chọn.',
            'label_ids.*.exists' => 'Có nhãn không tồn tại trong danh sách đã chọn.',
            'scopes.max' => 'Mỗi dự án chỉ có một phạm vi triển khai.',
            'scopes.*.scope_type.required' => 'Phạm vi triển khai không được để trống.',
            'scopes.*.scope_type.in' => 'Phạm vi triển khai không hợp lệ.',
            'scopes.*.department_id.exists' => 'Phòng ban đã chọn không tồn tại.',
            'scopes.*.weight_percent.min' => 'Tỷ trọng % KPI không hợp lệ.',
            'scopes.*.weight_percent.max' => 'Tỷ trọng % KPI tối đa là 100%.',
        ];
    }
}
