<?php

namespace Modules\Evaluation\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublishEvaluationConfigVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:500'],
            'effective_from' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'notes.max' => 'Ghi chú không được quá 500 ký tự.',
            'effective_from.date' => 'Ngày bắt đầu áp dụng không hợp lệ.',
        ];
    }
}
