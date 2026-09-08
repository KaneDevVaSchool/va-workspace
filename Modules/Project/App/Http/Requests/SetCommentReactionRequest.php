<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Project\App\Models\Comment;

class SetCommentReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(Comment::REACTION_TYPES)],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Vui lòng chọn 1 loại cảm xúc.',
            'type.in' => 'Loại cảm xúc không hợp lệ.',
        ];
    }
}
