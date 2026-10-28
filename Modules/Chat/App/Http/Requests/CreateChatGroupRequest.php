<?php

namespace Modules\Chat\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateChatGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:80'],
            'user_ids' => ['required', 'array', 'min:1', 'max:49'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Vui lòng nhập tên nhóm.',
            'title.max' => 'Tên nhóm tối đa 80 ký tự.',
            'user_ids.required' => 'Chọn ít nhất một thành viên.',
            'user_ids.min' => 'Chọn ít nhất một thành viên.',
            'user_ids.max' => 'Nhóm chỉ có tối đa 50 thành viên.',
            'user_ids.*.exists' => 'Có thành viên không tồn tại.',
            'user_ids.*.distinct' => 'Danh sách thành viên bị trùng.',
        ];
    }
}
