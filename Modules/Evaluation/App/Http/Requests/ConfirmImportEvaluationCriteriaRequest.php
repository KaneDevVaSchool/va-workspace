<?php

namespace Modules\Evaluation\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bước 2 của luồng nhập Excel — nhận JSON các dòng đã qua bước preview
 * (Modules\Evaluation\App\Http\Requests\ImportEvaluationCriteriaRequest),
 * KHÔNG nhận lại file. Shape khớp với `data` trong
 * EvaluationCriteriaExcelImporter::preview().
 */
class ConfirmImportEvaluationCriteriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.criterion_type_id' => ['nullable', 'integer', 'min:1'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.type' => ['required', 'string', Rule::in(['scale', 'behavior'])],
            'rows.*.description' => ['nullable', 'string'],
            'rows.*.levels' => ['required', 'array', 'min:1'],
            'rows.*.levels.*.code' => ['nullable', 'string', 'max:20'],
            'rows.*.levels.*.label' => ['required', 'string', 'max:255'],
            'rows.*.levels.*.description' => ['nullable', 'string'],
            'rows.*.levels.*.score' => ['required', 'numeric'],
            'rows.*.is_active' => ['sometimes', 'boolean'],
            'rows.*.allow_half' => ['sometimes', 'boolean'],
            'rows.*.use_in_evaluation' => ['sometimes', 'boolean'],
            'rows.*.sort_order' => ['sometimes', 'integer', 'min:0'],
            'rows.*.row' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'rows.required' => 'Không có dòng nào để nhập.',
            'rows.min' => 'Không có dòng nào để nhập.',
            'rows.*.name.required' => 'Thiếu tên tiêu chí.',
            'rows.*.type.required' => 'Thiếu cách chấm.',
            'rows.*.levels.required' => 'Thiếu các mức chấm điểm.',
        ];
    }
}
