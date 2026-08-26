<?php

namespace Modules\WorkspaceConfig\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\WorkspaceConfig\App\Services\DepartmentSidebarConfigService;

class ReorderSidebarLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sections = implode(',', array_keys(DepartmentSidebarConfigService::SECTIONS));

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_key' => ['required', 'string'],
            'items.*.section' => ['required', 'string', 'in:'.$sections],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $keys = collect($this->input('items', []))->pluck('menu_key')->filter()->all();
            if (count($keys) !== count(array_unique($keys))) {
                $validator->errors()->add('items', 'Danh sách mục menu bị trùng.');
            }
        });
    }
}
