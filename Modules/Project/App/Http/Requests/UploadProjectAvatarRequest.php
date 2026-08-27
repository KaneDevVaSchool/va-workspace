<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadProjectAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware + Controller đã kiểm tra quyền.
    }

    public function rules(): array
    {
        return [
            'avatar' => ['required', 'file', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => 'Vui lòng chọn ảnh đại diện.',
            'avatar.image' => 'Ảnh đại diện phải là file hình ảnh.',
            'avatar.max' => 'Ảnh đại diện không được vượt quá 5MB.',
            'avatar.mimes' => 'Định dạng ảnh không được hỗ trợ.',
        ];
    }
}
