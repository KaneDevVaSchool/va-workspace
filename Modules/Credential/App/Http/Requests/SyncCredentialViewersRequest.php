<?php

namespace Modules\Credential\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Đồng bộ toàn bộ danh sách người được cấp quyền xem thông tin đăng nhập
 * trong 1 lần (picker multi-select ở CredentialDetail.vue) — thay cho
 * StoreCredentialViewerRequest (thêm 1 người/lần). `user_ids` dùng
 * `present` (không `required`) để cho phép gửi mảng rỗng (bỏ hết viewer
 * là 1 thao tác hợp lệ).
 */
class SyncCredentialViewersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_ids' => ['present', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.*.exists' => 'Có người dùng không tồn tại trong danh sách chọn.',
        ];
    }
}
