<?php

namespace Modules\Dashboard\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Project\App\Enums\ProjectEnums;

class CompanyProjectsListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Middleware permission:dashboard.view_company đã chặn ở route.
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:'.implode(',', ProjectEnums::STATUSES)],
            'department_id' => ['nullable', 'integer', 'min:1'],
            'health' => ['nullable', 'string', 'in:good,warning,risk'],
            'overdue_bucket' => ['nullable', 'string', 'in:0_3,4_7,8_14,over_14'],
            'overdue_only' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'string', 'in:end_date,status,name'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
            // max cao hơn bảng danh sách thường — bảng dự án nhóm theo phòng
            // ban ở FE cần tải toàn bộ 1 lần (không phân trang cắt giữa
            // nhóm), số dự án toàn công ty không lớn tới mức cần giới hạn 100.
            'per_page' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
