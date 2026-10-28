<?php

namespace Modules\Identity\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnsubscribePushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'endpoint.required' => 'Thiếu endpoint.',
        ];
    }
}
