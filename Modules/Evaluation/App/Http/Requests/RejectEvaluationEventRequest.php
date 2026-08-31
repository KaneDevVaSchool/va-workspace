<?php

namespace Modules\Evaluation\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectEvaluationEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reject_reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reject_reason.required' => 'Cần nêu lý do từ chối.',
            'reject_reason.max' => 'Lý do từ chối không được quá 500 ký tự.',
        ];
    }
}
