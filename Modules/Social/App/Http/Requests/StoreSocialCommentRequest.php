<?php

namespace Modules\Social\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSocialCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string', 'max:8000', 'required_without:attachments'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file', 'max:10240',
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xlsx,xls',
            ],
            'parent_comment_id' => ['nullable', 'integer', 'exists:social_post_comments,id'],
            'mentioned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required_without' => 'Bình luận phải có nội dung hoặc ít nhất 1 tệp đính kèm.',
            'content.max' => 'Bình luận không được vượt quá 8000 ký tự.',
            'attachments.max' => 'Chỉ được đính kèm tối đa 5 tệp mỗi bình luận.',
            'attachments.*.max' => 'Mỗi tệp đính kèm không được vượt quá 10MB.',
            'attachments.*.mimes' => 'Chỉ chấp nhận ảnh (jpg, png, gif, webp) hoặc tài liệu (pdf, doc, docx, xlsx, xls).',
            'parent_comment_id.exists' => 'Bình luận gốc không tồn tại.',
            'mentioned_user_id.exists' => 'Người được nhắc không tồn tại.',
        ];
    }
}
