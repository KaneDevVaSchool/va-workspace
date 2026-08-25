<?php

namespace Modules\Social\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShareSocialPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'caption' => ['nullable', 'string', 'max:5000'],
            'post_scope' => ['sometimes', 'in:company,department,personal,group'],
            'wall_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'group_id' => ['required_if:post_scope,group', 'nullable', 'integer', 'exists:social_groups,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'caption.max' => 'Nội dung chia sẻ không được vượt quá 5000 ký tự.',
        ];
    }
}
