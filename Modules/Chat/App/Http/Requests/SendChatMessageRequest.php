<?php

namespace Modules\Chat\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Chat\App\Models\Message;

class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('message_type', Message::TYPE_TEXT);

        return [
            'message' => [
                Rule::requiredIf($type !== Message::TYPE_STICKER && ! $this->has('attachments')),
                'nullable',
                'string',
                'max:5000',
            ],
            'message_type' => ['nullable', 'string', Rule::in([
                Message::TYPE_TEXT,
                Message::TYPE_STICKER,
                Message::TYPE_VOICE,
                Message::TYPE_POLL,
            ])],
            'sticker_id' => [
                Rule::requiredIf($type === Message::TYPE_STICKER),
                'nullable',
                'string',
                'max:64',
            ],
            'reply_to_id' => ['nullable', 'integer'],
            'voice_duration' => ['nullable', 'integer', 'min:1', 'max:180'],
            'mention_ids' => ['nullable', 'array', 'max:20'],
            'mention_ids.*' => ['integer'],
            'poll_options' => [
                Rule::requiredIf($type === Message::TYPE_POLL),
                'nullable',
                'array',
                'min:2',
                'max:6',
            ],
            'poll_options.*' => ['string', 'max:80'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xlsx,xls,ppt,pptx,txt,csv,zip,webm,ogg,mp3,m4a,wav,mp4',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Vui lòng nhập nội dung hoặc chọn tệp.',
            'message.max' => 'Tin nhắn không được vượt quá 5000 ký tự.',
            'sticker_id.required' => 'Vui lòng chọn sticker.',
            'attachments.max' => 'Chỉ được đính kèm tối đa 5 tệp mỗi tin nhắn.',
            'attachments.*.max' => 'Mỗi tệp không được vượt quá 10MB.',
            'attachments.*.mimes' => 'Chỉ chấp nhận ảnh, âm thanh, PDF, Word, Excel, PowerPoint, TXT, CSV hoặc ZIP.',
        ];
    }
}
