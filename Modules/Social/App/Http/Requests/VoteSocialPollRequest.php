<?php

namespace Modules\Social\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoteSocialPollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'option_id' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'option_id.required' => 'Vui lòng chọn 1 phương án.',
        ];
    }
}
