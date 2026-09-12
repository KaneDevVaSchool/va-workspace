<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm tra qua middleware route ('permission:feedback.create')
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:5000'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Vui lòng nhập nội dung phản hồi.',
            'content.max' => 'Nội dung không được vượt quá 5000 ký tự.',
            'rating.min' => 'Đánh giá phải từ 1 đến 5.',
            'rating.max' => 'Đánh giá phải từ 1 đến 5.',
        ];
    }
}
