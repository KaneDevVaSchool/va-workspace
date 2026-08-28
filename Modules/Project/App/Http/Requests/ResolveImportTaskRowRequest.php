<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sửa lỗi tại chỗ 1 dòng trong bảng xem trước nhập Excel — nhận đúng các
 * ô text thô (cùng shape TaskExcelImporter::readCells()) mà người dùng
 * vừa sửa, để re-resolve lại validate/preview cho riêng dòng đó.
 */
class ResolveImportTaskRowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm tra qua middleware route ('permission:task.create')
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:64'],
            'project_code' => ['nullable', 'string', 'max:64'],
            'title' => ['nullable', 'string', 'max:255'],
            'type_input' => ['nullable', 'string', 'max:64'],
            'status_input' => ['nullable', 'string', 'max:64'],
            'priority_input' => ['nullable', 'string', 'max:64'],
            'assignee_input' => ['nullable', 'string', 'max:255'],
            'manager_input' => ['nullable', 'string', 'max:255'],
            'start_input' => ['nullable', 'string', 'max:32'],
            'end_input' => ['nullable', 'string', 'max:32'],
            'progress_type_input' => ['nullable', 'string', 'max:64'],
            'progress_percent_input' => ['nullable', 'string', 'max:16'],
            'progress_number_input' => ['nullable', 'string', 'max:32'],
            'progress_total_input' => ['nullable', 'string', 'max:32'],
            'unit' => ['nullable', 'string', 'max:50'],
            'estimated_hours_input' => ['nullable', 'string', 'max:32'],
            'weight_input' => ['nullable', 'string', 'max:16'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
