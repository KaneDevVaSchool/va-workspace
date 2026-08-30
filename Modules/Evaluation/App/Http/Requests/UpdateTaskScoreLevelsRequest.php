<?php

namespace Modules\Evaluation\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskScoreLevelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codes' => ['present', 'array'],
            'codes.*' => ['string', 'max:32'],
        ];
    }

    public function messages(): array
    {
        return [
            'codes.present' => 'Danh sách mức gói không hợp lệ.',
            'codes.array' => 'Danh sách mức gói không hợp lệ.',
            'codes.*.max' => 'Mã mức quá dài.',
        ];
    }
};
