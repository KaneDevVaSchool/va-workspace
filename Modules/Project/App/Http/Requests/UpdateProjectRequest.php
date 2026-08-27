<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
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
            'type' => ['sometimes', 'required', 'string', 'in:'.implode(',', ProjectEnums::TYPES)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'lead_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'executing_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'progress_method' => ['sometimes', 'string', 'in:'.implode(',', ProjectEnums::PROGRESS_METHODS)],
            'status' => ['sometimes', 'string', 'in:'.implode(',', ProjectEnums::STATUSES)],
            'importance' => ['sometimes', 'string', 'in:'.implode(',', ProjectEnums::IMPORTANCE_LEVELS)],
            'description' => ['nullable', 'string', 'max:5000'],

            'member_ids' => ['sometimes', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],

            'follower_ids' => ['sometimes', 'array'],
            'follower_ids.*' => ['integer', 'exists:users,id'],

            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => ['integer', 'exists:project_labels,id'],

            'scopes' => ['sometimes', 'array'],
            'scopes.*.scope_type' => ['required', 'string', 'in:'.implode(',', ProjectEnums::SCOPE_TYPES)],
            'scopes.*.department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'scopes.*.weight_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Loại dự án là bắt buộc.',
            'type.in' => 'Loại dự án không hợp lệ.',
            'name.required' => 'Tên dự án là bắt buộc.',
            'name.max' => 'Tên dự án không được vượt quá 255 ký tự.',
            'lead_user_id.exists' => 'Người phụ trách chính không tồn tại.',
            'start_date.date' => 'Ngày bắt đầu không hợp lệ.',
            'end_date.date' => 'Ngày kết thúc không hợp lệ.',
            'progress_method.in' => 'Phương pháp tính tiến độ không hợp lệ.',
            'status.in' => 'Trạng thái dự án không hợp lệ.',
            'importance.in' => 'Mức độ quan trọng không hợp lệ.',
            'member_ids.*.exists' => 'Có người thực hiện không tồn tại trong danh sách đã chọn.',
            'follower_ids.*.exists' => 'Có người theo dõi không tồn tại trong danh sách đã chọn.',
            'executing_department_id.exists' => 'Phòng ban thực hiện không tồn tại.',
            'label_ids.*.exists' => 'Có nhãn không tồn tại trong danh sách đã chọn.',
            'scopes.*.scope_type.required' => 'Phạm vi triển khai không được để trống.',
            'scopes.*.scope_type.in' => 'Phạm vi triển khai không hợp lệ.',
            'scopes.*.department_id.exists' => 'Phòng ban đã chọn không tồn tại.',
            'scopes.*.weight_percent.required' => 'Tỷ trọng % KPI không được để trống.',
            'scopes.*.weight_percent.min' => 'Tỷ trọng % KPI không hợp lệ.',
            'scopes.*.weight_percent.max' => 'Tỷ trọng % KPI tối đa là 100%.',
        ];
    }
}
