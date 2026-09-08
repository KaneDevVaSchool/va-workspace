<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:project_folders,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên thư mục không được để trống.',
            'name.max' => 'Tên thư mục không được vượt quá 255 ký tự.',
            'parent_id.exists' => 'Thư mục cha không tồn tại.',
        ];
    }
}
