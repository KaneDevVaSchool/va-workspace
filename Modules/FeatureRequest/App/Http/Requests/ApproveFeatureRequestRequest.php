<?php

namespace Modules\FeatureRequest\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveFeatureRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expected_done_at' => ['nullable', 'date'],
            'progress_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'expected_done_at.date' => 'Ngày hoàn thành dự kiến không hợp lệ.',
            'progress_note.max' => 'Ghi chú tiến độ không được quá 500 ký tự.',
        ];
    }
}
