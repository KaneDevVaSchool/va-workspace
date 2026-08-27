<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Project\App\Models\ProjectLabel;

class StoreProjectLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Mọi user đã đăng nhập được tạo nhãn mới khi gõ-tìm-tạo trong form dự án.
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:project_labels,name'],
            'color' => ['required', 'string', 'in:'.implode(',', ProjectLabel::COLORS)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên nhãn là bắt buộc.',
            'name.unique' => 'Nhãn này đã tồn tại.',
            'color.in' => 'Màu nhãn không hợp lệ.',
        ];
    }
}
