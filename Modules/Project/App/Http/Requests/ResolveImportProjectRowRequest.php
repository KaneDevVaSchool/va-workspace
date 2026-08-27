<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Project\App\Services\ProjectService;

/**
 * Sửa lỗi tại chỗ 1 dòng trong bảng xem trước nhập Excel — nhận đúng các
 * ô text thô (cùng shape ProjectExcelImporter::readCells()) mà người dùng
 * vừa sửa, để re-resolve lại validate/preview cho riêng dòng đó.
 */
class ResolveImportProjectRowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && app(ProjectService::class)->userCanCreate($this->user());
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:64'],
            'name' => ['nullable', 'string', 'max:255'],
            'type_input' => ['nullable', 'string', 'max:64'],
            'exec_dept_name' => ['nullable', 'string', 'max:255'],
            'lead_input' => ['nullable', 'string', 'max:255'],
            'members_input' => ['nullable', 'string', 'max:2000'],
            'followers_input' => ['nullable', 'string', 'max:2000'],
            'labels_input' => ['nullable', 'string', 'max:1000'],
            'status_input' => ['nullable', 'string', 'max:64'],
            'importance_input' => ['nullable', 'string', 'max:64'],
            'start_input' => ['nullable', 'string', 'max:32'],
            'end_input' => ['nullable', 'string', 'max:32'],
            'progress_method_input' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
