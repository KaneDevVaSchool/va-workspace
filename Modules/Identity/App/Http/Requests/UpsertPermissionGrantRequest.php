<?php

namespace Modules\Identity\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Identity\App\Models\Role;

class UpsertPermissionGrantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route đã bọc middleware auth + role:super_admin
    }

    public function rules(): array
    {
        return [
            'role_code' => [
                'required',
                'string',
                Rule::in(
                    Role::query()->where('code', '!=', 'super_admin')->pluck('code')->all()
                ),
            ],
            'permission_key' => ['required', 'string'],
            'granted' => ['required', 'boolean'],
            'scope_type' => ['required', Rule::in(['global', 'department', 'team'])],
            'scope_id' => [
                'required_unless:scope_type,global',
                'nullable',
                'integer',
                function ($attribute, $value, $fail) {
                    if ($value === null) {
                        return;
                    }

                    $scopeType = $this->input('scope_type');
                    $table = match ($scopeType) {
                        'department' => 'departments',
                        'team' => 'teams',
                        default => null,
                    };

                    if ($table === null) {
                        return;
                    }

                    $exists = DB::table($table)->where('id', $value)->exists();
                    if (! $exists) {
                        $fail("Không tìm thấy {$scopeType} tương ứng với scope_id đã chọn.");
                    }
                },
            ],
        ];
    }
}
