<?php

namespace Modules\FeatureRequest\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\FeatureRequest\App\Models\FeatureRequest;

/**
 * Validate điều kiện xuất Excel — quyền feature_request.review kiểm trong
 * Controller (giống các action khác của module).
 */
class ExportFeatureRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in([
                FeatureRequest::STATUS_PENDING,
                FeatureRequest::STATUS_REVIEWING,
                FeatureRequest::STATUS_APPROVED,
                FeatureRequest::STATUS_REJECTED,
                FeatureRequest::STATUS_DONE,
            ]), 'required_if:export_kind,status'],
            'department_id' => ['nullable', 'string', 'max:32', 'required_if:export_kind,department'],
            'date_from' => ['nullable', 'date', 'required_if:export_kind,date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from', 'required_if:export_kind,date'],
            'export_kind' => ['nullable', 'string', Rule::in(['filter', 'date', 'department', 'status'])],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.required_if' => 'Vui lòng chọn ngày bắt đầu.',
            'date_to.required_if' => 'Vui lòng chọn ngày kết thúc.',
            'date_to.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            'department_id.required_if' => 'Vui lòng chọn phòng ban cần xuất.',
            'status.required_if' => 'Vui lòng chọn trạng thái cần xuất.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }

    /** @return array<string, string> */
    public function filters(): array
    {
        $data = $this->validated();

        return [
            'q' => trim((string) ($data['q'] ?? '')),
            'status' => trim((string) ($data['status'] ?? '')),
            'department_id' => trim((string) ($data['department_id'] ?? '')),
            'date_from' => trim((string) ($data['date_from'] ?? '')),
            'date_to' => trim((string) ($data['date_to'] ?? '')),
        ];
    }

    public function exportKind(): string
    {
        return (string) ($this->validated()['export_kind'] ?? 'filter');
    }
}
