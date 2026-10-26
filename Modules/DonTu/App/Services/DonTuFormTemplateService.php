<?php

namespace Modules\DonTu\App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\DonTu\App\Models\DonTuFormTemplate;
use Modules\DonTu\App\Models\DonTuWorkflowForm;

class DonTuFormTemplateService
{
    private const FIELD_TYPES = [
        'text', 'textarea', 'checkbox', 'radio', 'select', 'date', 'file', 'currency', 'signature',
    ];

    public function listForUser(User $user, ?int $departmentId): Collection
    {
        $deptId = $departmentId ?? (int) ($user->department_id ?? 0);

        return DonTuFormTemplate::query()
            ->where(function ($q) use ($deptId) {
                $q->whereNull('department_id');
                if ($deptId > 0) {
                    $q->orWhere('department_id', $deptId);
                }
            })
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (DonTuFormTemplate $t) => $this->present($t));
    }

    public function find(int $id): ?DonTuFormTemplate
    {
        return DonTuFormTemplate::query()->find($id);
    }

    public function present(DonTuFormTemplate $template): array
    {
        $fields = $template->schema_json['fields'] ?? [];

        return [
            'id' => $template->id,
            'code' => $template->code,
            'name' => $template->name,
            'description' => $template->description,
            'scope' => $template->scope,
            'department_id' => $template->department_id,
            'status' => $template->status,
            'version' => $template->version,
            'field_count' => is_array($fields) ? count($fields) : 0,
            'schema_json' => $template->schema_json ?? ['fields' => []],
            'branding_json' => $template->branding_json,
            'created_at' => $template->created_at?->toIso8601String(),
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }

    public function create(User $user, array $data): DonTuFormTemplate
    {
        $code = $this->normalizeCode($data['code'] ?? $data['name'] ?? 'MAU');
        if (DonTuFormTemplate::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => 'Mã mẫu đã tồn tại.']);
        }

        $deptId = array_key_exists('department_id', $data) ? $data['department_id'] : $user->department_id;

        return DonTuFormTemplate::query()->create([
            'code' => $code,
            'name' => trim((string) $data['name']),
            'description' => $data['description'] ?? null,
            'scope' => $data['scope'] ?? 'internal',
            'department_id' => $deptId,
            'status' => 'draft',
            'version' => 1,
            'schema_json' => ['fields' => []],
            'branding_json' => $data['branding_json'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    public function update(DonTuFormTemplate $template, User $user, array $data): DonTuFormTemplate
    {
        if (isset($data['name'])) {
            $template->name = trim((string) $data['name']);
        }
        if (array_key_exists('description', $data)) {
            $template->description = $data['description'];
        }
        if (isset($data['scope'])) {
            $template->scope = $data['scope'];
        }
        if (isset($data['status']) && in_array($data['status'], ['draft', 'active', 'archived'], true)) {
            $template->status = $data['status'];
            if ($data['status'] === 'active') {
                $template->version = max(1, (int) $template->version);
            }
        }
        if (isset($data['schema_json'])) {
            $this->assertSchemaValid($data['schema_json']);
            $template->schema_json = $data['schema_json'];
            if ($template->status === 'active') {
                $template->version = (int) $template->version + 1;
            }
        }
        if (array_key_exists('branding_json', $data)) {
            $template->branding_json = $data['branding_json'];
        }

        $template->updated_by = $user->id;
        $template->save();

        return $template->fresh();
    }

    public function duplicate(DonTuFormTemplate $source, User $user): DonTuFormTemplate
    {
        $base = $source->code.'_COPY';
        $code = $base;
        $i = 1;
        while (DonTuFormTemplate::query()->where('code', $code)->exists()) {
            $code = $base.'_'.$i;
            $i++;
        }

        return DonTuFormTemplate::query()->create([
            'code' => $code,
            'name' => $source->name.' (bản sao)',
            'description' => $source->description,
            'scope' => $source->scope,
            'department_id' => $source->department_id ?? $user->department_id,
            'status' => 'draft',
            'version' => 1,
            'schema_json' => $source->schema_json ?? ['fields' => []],
            'branding_json' => $source->branding_json,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    public function archive(DonTuFormTemplate $template): void
    {
        $linked = DonTuWorkflowForm::query()
            ->where('form_template_id', $template->id)
            ->exists();

        if ($linked && $template->status === 'active') {
            throw ValidationException::withMessages([
                'status' => 'Mẫu đang gắn quy trình — chỉ được ẩn (lưu trữ), không xóa.',
            ]);
        }

        $template->status = 'archived';
        $template->save();
    }

    public function destroy(DonTuFormTemplate $template): void
    {
        if ($template->status === 'active') {
            throw ValidationException::withMessages([
                'status' => 'Không xóa mẫu đang hoạt động. Hãy chuyển sang lưu trữ trước.',
            ]);
        }

        if (DonTuWorkflowForm::query()->where('form_template_id', $template->id)->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Mẫu đã từng gắn quy trình — chỉ được lưu trữ.',
            ]);
        }

        $template->delete();
    }

    private function assertSchemaValid(array $schema): void
    {
        $fields = $schema['fields'] ?? [];
        if (! is_array($fields)) {
            throw ValidationException::withMessages(['schema_json' => 'Danh sách trường không hợp lệ.']);
        }

        $keys = [];
        foreach ($fields as $idx => $field) {
            if (! is_array($field)) {
                throw ValidationException::withMessages(['schema_json' => 'Trường #'.($idx + 1).' không hợp lệ.']);
            }
            $key = $field['key'] ?? '';
            if ($key === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
                throw ValidationException::withMessages(['schema_json' => 'Mã trường phải là chữ thường, số và gạch dưới.']);
            }
            if (isset($keys[$key])) {
                throw ValidationException::withMessages(['schema_json' => "Mã trường trùng: {$key}"]);
            }
            $keys[$key] = true;

            $type = $field['type'] ?? 'text';
            if (! in_array($type, self::FIELD_TYPES, true)) {
                throw ValidationException::withMessages(['schema_json' => "Kiểu trường không hỗ trợ: {$type}"]);
            }

            if ($type === 'signature' && empty(trim((string) ($field['commitment_text'] ?? '')))) {
                throw ValidationException::withMessages(['schema_json' => "Ô ký «{$key}» cần câu cam kết."]);
            }

            if ($type === 'currency' && isset($field['default']) && (float) $field['default'] < 0) {
                throw ValidationException::withMessages(['schema_json' => 'Số tiền không được âm.']);
            }
        }
    }

    private function normalizeCode(string $raw): string
    {
        $code = Str::upper(Str::slug($raw, '_'));
        $code = preg_replace('/[^A-Z0-9_]/', '', $code) ?: 'MAU';

        return Str::limit($code, 60, '');
    }
}
