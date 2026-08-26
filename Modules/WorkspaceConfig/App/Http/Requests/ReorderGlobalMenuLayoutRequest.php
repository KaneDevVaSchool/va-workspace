<?php

namespace Modules\WorkspaceConfig\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderGlobalMenuLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_key' => ['required', 'string', 'max:120'],
            'items.*.section' => ['required', 'string', 'max:80'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
