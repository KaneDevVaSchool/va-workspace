<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertTaskScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm tra qua middleware route ('permission:task.approve')
    }

    public function rules(): array
    {
        return [
            'rating_score' => ['nullable', 'numeric', 'min:0'],
            // rating_result là text tự do, KHÔNG enum DB cứng — kết quả
            // đánh giá tuỳ cấu hình evaluation tương lai (chưa dựng).
            'rating_result' => ['nullable', 'string', 'max:100'],
            'rating_desc' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rating_score.numeric' => 'Điểm số không hợp lệ.',
            'rating_score.min' => 'Điểm số không được âm.',
            'rating_result.max' => 'Kết quả đánh giá không được vượt quá 100 ký tự.',
            'rating_desc.max' => 'Ý kiến đánh giá không được vượt quá 5000 ký tự.',
        ];
    }
}
