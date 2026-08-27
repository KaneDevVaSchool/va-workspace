<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Project\App\Enums\ProjectEnums;
use Modules\Project\App\Services\ProjectService;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route chỉ còn middleware 'auth' — kiểm tra quyền tạo dự án (role sẵn
        // có 'project.create' HOẶC nằm trong allowlist mở rộng, mục C) chuyển
        // vào đây để Laravel tự trả 403 khi false.
        return $this->user() !== null && app(ProjectService::class)->userCanCreate($this->user());
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'lead_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'lead_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            // Chỉ super_admin / director_officer mới gửi được field này (mục C) —
            // ProjectService bỏ qua nếu người tạo không có quyền, xem create().
            'owner_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'executing_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'executing_department_ids' => ['nullable', 'array'],
            'executing_department_ids.*' => ['integer', 'exists:departments,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'progress_method' => ['nullable', 'string', 'in:'.implode(',', ProjectEnums::PROGRESS_METHODS)],
            'status' => ['nullable', 'string', 'in:'.implode(',', ProjectEnums::STATUSES)],
            'importance' => ['nullable', 'string', 'in:'.implode(',', ProjectEnums::IMPORTANCE_LEVELS)],
            'description' => ['nullable', 'string', 'max:5000'],

            'shift_task_dates_with_project' => ['nullable', 'boolean'],
            'hide_cross_tasks_from_assignees' => ['nullable', 'boolean'],
            'hide_child_tasks_from_followers' => ['nullable', 'boolean'],
            'constrain_task_dates_to_project' => ['nullable', 'boolean'],

            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],

            'follower_ids' => ['nullable', 'array'],
            'follower_ids.*' => ['integer', 'exists:users,id'],

            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer', 'exists:project_labels,id'],

            'scopes' => ['nullable', 'array', 'max:1'],
            'scopes.*.scope_type' => ['required', 'string', 'in:'.implode(',', ProjectEnums::SCOPE_TYPES)],
            'scopes.*.department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'scopes.*.weight_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
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
            'owner_department_id.exists' => 'Phòng ban giao không tồn tại.',
            'start_date.date' => 'Ngày bắt đầu không hợp lệ.',
            'end_date.date' => 'Ngày kết thúc không hợp lệ.',
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
