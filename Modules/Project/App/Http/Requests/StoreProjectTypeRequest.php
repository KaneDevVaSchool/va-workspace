<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Mọi user đã đăng nhập được tạo loại dự án mới khi chọn-tạo trong form dự án.
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:project_types,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên loại dự án là bắt buộc.',
            'name.unique' => 'Loại dự án này đã tồn tại.',
        ];
    }
}
