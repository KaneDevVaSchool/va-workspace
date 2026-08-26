<?php

namespace Modules\Social\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSocialPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string', 'max:8000'],
            'as_system_announcement' => ['sometimes', 'boolean'],
            'post_scope' => ['sometimes', 'in:company,department,personal,group'],
            'wall_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'group_id' => ['required_if:post_scope,group', 'nullable', 'integer', 'exists:social_groups,id'],
            'department_visibility_mode' => ['sometimes', 'in:all,include,exclude'],
            'department_visibility_ids' => ['required_if:department_visibility_mode,include,exclude', 'array', 'max:200'],
            'department_visibility_ids.*' => ['integer', 'exists:departments,id'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                'file', 'max:10240',
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xlsx,xls',
            ],
            'poll' => ['sometimes', 'array'],
            'poll.title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'poll.content' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'poll.image' => ['sometimes', 'nullable', 'image', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp'],
            'poll.options' => ['required_with:poll', 'array', 'min:2', 'max:10'],
            'poll.options.*' => ['required', 'string', 'max:200'],
            'poll.allow_multiple' => ['sometimes', 'boolean'],
            'poll.ends_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->hasContent() || $this->hasFile('attachments') || $this->hasPoll()) {
                return;
            }

            $validator->errors()->add('content', 'Bài viết phải có nội dung, tệp đính kèm hoặc bình chọn.');
        });
    }

    public function messages(): array
    {
        return [
            'content.max' => 'Nội dung bài viết không được vượt quá 8000 ký tự.',
            'attachments.max' => 'Chỉ được đính kèm tối đa 10 tệp mỗi bài viết.',
            'attachments.*.max' => 'Mỗi tệp đính kèm không được vượt quá 10MB.',
            'attachments.*.mimes' => 'Chỉ chấp nhận ảnh (jpg, png, gif, webp) hoặc tài liệu (pdf, doc, docx, xlsx, xls).',
            'poll.title.max' => 'Tiêu đề bình chọn không được vượt quá 200 ký tự.',
            'poll.content.max' => 'Nội dung bình chọn không được vượt quá 2000 ký tự.',
            'poll.image.image' => 'Ảnh bình chọn không hợp lệ.',
            'poll.image.max' => 'Ảnh bình chọn không được vượt quá 10MB.',
            'poll.image.mimes' => 'Ảnh bình chọn chỉ chấp nhận jpg, png, gif, webp.',
            'poll.options.required_with' => 'Bình chọn cần ít nhất 2 phương án.',
            'poll.options.min' => 'Bình chọn cần ít nhất 2 phương án.',
            'poll.options.max' => 'Bình chọn tối đa 10 phương án.',
            'poll.options.*.required' => 'Phương án không được để trống.',
            'poll.options.*.max' => 'Mỗi phương án không được vượt quá 200 ký tự.',
            'poll.ends_at.after' => 'Hạn bình chọn phải ở tương lai.',
            'department_visibility_ids.required_if' => 'Chọn ít nhất 1 phòng ban.',
        ];
    }

    private function hasContent(): bool
    {
        return filled(trim(strip_tags((string) $this->input('content', ''))));
    }

    private function hasPoll(): bool
    {
        $options = collect($this->input('poll.options', []))
            ->map(fn ($label) => trim((string) $label))
            ->filter(fn ($label) => $label !== '');

        return $options->count() >= 2;
    }
}
