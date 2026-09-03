<?php

namespace Modules\Report\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Đổi cột tiêu chí / cột điểm trên bảng chấm điểm — lưu thành phụ lục 1.1+.
 */
class UpdatePersonnelEvaluationDisplayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'criterion_ids' => ['present', 'array'],
            'criterion_ids.*' => ['integer'],
            'column_keys' => ['sometimes', 'array'],
            'column_keys.*' => ['string', 'max:60'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'criterion_ids.present' => 'Chưa chọn cột tiêu chí để lưu phụ lục.',
        ];
    }
}
