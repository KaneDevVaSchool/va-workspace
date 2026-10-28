<?php

namespace Modules\Identity\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Identity\App\Rules\AllowedPushEndpoint;

class SubscribePushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:4096', new AllowedPushEndpoint],
            // Cột public_key / auth_token là string(255).
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
