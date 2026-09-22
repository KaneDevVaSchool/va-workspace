<?php

namespace Modules\Chat\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenChatConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Vui lòng chọn người muốn trò chuyện.',
            'user_id.exists' => 'Người dùng không tồn tại.',
        ];
    }
}
