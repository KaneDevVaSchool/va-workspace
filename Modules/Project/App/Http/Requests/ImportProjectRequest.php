<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Project\App\Services\ProjectService;

class ImportProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && app(ProjectService::class)->userCanCreate($this->user());
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Vui lòng chọn file Excel cần nhập.',
            'file.mimes' => 'File phải đúng định dạng .xlsx.',
            'file.max' => 'File không được vượt quá 5MB.',
        ];
    }
}
