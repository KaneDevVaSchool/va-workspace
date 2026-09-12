<?php

namespace Modules\Credential\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Credential\App\Enums\CredentialEnums;

class UpdateCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->allows('credential.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'provider_id' => ['nullable', 'integer', 'exists:credential_providers,id'],
            'account_type' => ['sometimes', 'required', 'string', 'in:'.implode(',', CredentialEnums::ACCOUNT_TYPES)],

            'username' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'max:1000'],

            'is_google_login' => ['nullable', 'boolean'],
            'google_account_owner' => ['nullable', 'string', 'max:255'],

            'server_name' => ['nullable', 'string', 'max:255'],
            'vps_cluster' => ['nullable', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'database_name' => ['nullable', 'string', 'max:255'],
            'is_root_account' => ['nullable', 'boolean'],
            'is_iam_account' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],

            'purchased_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'monthly_cost' => ['nullable', 'numeric', 'min:0'],
            'cost_hidden' => ['nullable', 'boolean'],
            'currency' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên tài khoản là bắt buộc.',
            'account_type.required' => 'Loại tài khoản là bắt buộc.',
            'account_type.in' => 'Loại tài khoản không hợp lệ.',
            'provider_id.exists' => 'Nhà cung cấp không tồn tại.',
            'email.email' => 'Email không đúng định dạng.',
            'purchased_at.date' => 'Ngày mua không hợp lệ.',
            'expires_at.date' => 'Ngày hết hạn không hợp lệ.',
            'monthly_cost.numeric' => 'Chi phí hằng tháng phải là số.',
            'monthly_cost.min' => 'Chi phí hằng tháng không được âm.',
        ];
    }
}
