<?php

namespace Modules\Project\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Project\App\Models\ProjectQuickItem;

class StoreProjectQuickItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $needsTitle = $this->input('kind') !== ProjectQuickItem::KIND_BASELINE
            && ! $this->filled('titles');

        return [
            'kind' => ['required', 'string', Rule::in(ProjectQuickItem::KINDS)],
            'title' => [$needsTitle ? 'required' : 'nullable', 'string', 'max:255'],
            'titles' => ['nullable', 'array', 'min:1'],
            'titles.*' => ['required', 'string', 'max:255'],
            'payload' => ['nullable', 'array'],
            'payload.variant' => ['nullable', 'string', 'max:40'],
            'payload.start_date' => ['nullable', 'date'],
            'payload.end_date' => ['nullable', 'date'],
            'payload.assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'payload.category_id' => ['nullable', 'integer', 'exists:project_quick_items,id'],
            'payload.phase_id' => ['nullable', 'integer', 'exists:project_quick_items,id'],
            'payload.note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'kind.required' => 'Loại mục là bắt buộc.',
            'kind.in' => 'Loại mục không hợp lệ.',
            'title.required' => 'Tên là bắt buộc.',
            'title.max' => 'Tên không được vượt quá 255 ký tự.',
            'titles.min' => 'Nhập ít nhất một công việc.',
            'titles.*.required' => 'Tên công việc không được để trống.',
        ];
    }
}
