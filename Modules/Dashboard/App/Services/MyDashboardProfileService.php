<?php

namespace Modules\Dashboard\App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Dashboard\App\Models\DashboardProfileChangeRequest;
use Modules\Identity\App\Hrm\Exceptions\HrmDatabaseUnavailable;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;
use Modules\Identity\App\Support\UserAvatarUrl;

class MyDashboardProfileService
{
    /** @var array<string, array{label: string, type: string, placeholder?: string, options?: list<array{value: string, label: string}>}> */
    public const EDITABLE_FIELDS = [
        'full_name' => ['label' => 'Họ và tên', 'type' => 'text'],
        'phone' => ['label' => 'Số điện thoại', 'type' => 'tel'],
        'personal_email' => ['label' => 'Email cá nhân', 'type' => 'email'],
        'company_email' => ['label' => 'Email công ty', 'type' => 'email'],
        'birthdate' => ['label' => 'Ngày sinh', 'type' => 'date'],
        'gender' => [
            'label' => 'Giới tính',
            'type' => 'select',
            'options' => [
                ['value' => 'male', 'label' => 'Nam'],
                ['value' => 'female', 'label' => 'Nữ'],
            ],
        ],
        'workplace' => ['label' => 'Nơi làm việc', 'type' => 'text'],
        'attendance_code' => ['label' => 'Mã chấm công', 'type' => 'text'],
        'manager_name' => [
            'label' => 'Quản lý trực tiếp',
            'type' => 'text',
            'placeholder' => 'Họ tên và mã NS, ví dụ: Nguyễn Văn A (VA010067)',
        ],
    ];

    public function __construct(
        private readonly HrmEmployeeDirectory $hrmEmployees,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws HrmDatabaseUnavailable
     */
    public function profile(User $user): array
    {
        $hrm = $this->resolveHrmProfile($user);
        $avatarUrl = filled($user->avatar_url)
            ? $user->avatar_url
            : ($hrm['avatar_url'] ?? null);

        return [
            'avatar_url' => $avatarUrl,
            'avatar_is_custom' => UserAvatarUrl::isUserUploaded($user->avatar_url),
            'workspace' => $this->presentWorkspace($user),
            'hrm' => $hrm,
            'editable_fields' => $this->editableFieldMeta($hrm),
            'pending_requests' => $this->pendingRequestsFor($user),
            'hrm_available' => HrmEmployeeDirectory::isConfigured(),
        ];
    }

    public function updateAvatar(User $user, UploadedFile $file): User
    {
        $previous = $this->workspaceAvatarPath($user->avatar_url);

        $path = $file->store('users/'.$user->id.'/avatar', 'public');
        $url = Storage::disk('public')->url($path);

        $user->forceFill(['avatar_url' => $url])->save();

        if ($previous !== null && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return $user->fresh(['department', 'company', 'concurrentPositions']);
    }

    /**
     * @param  array<string, mixed|null>  $changes
     */
    public function storeChangeRequest(User $user, array $changes, ?string $note): DashboardProfileChangeRequest
    {
        $hrm = $this->resolveHrmProfile($user);
        $normalized = [];

        foreach ($changes as $field => $requested) {
            $key = (string) $field;
            if (! array_key_exists($key, self::EDITABLE_FIELDS)) {
                continue;
            }

            $requestedValue = is_string($requested) ? trim($requested) : '';
            if ($requestedValue === '') {
                continue;
            }

            $current = $hrm !== null ? ($hrm[$key] ?? null) : null;
            $currentStr = is_string($current) ? trim($current) : (filled($current) ? (string) $current : '');

            if ($key === 'gender') {
                $requestedValue = $this->normalizeGenderValue($requestedValue) ?? $requestedValue;
                $currentStr = $this->normalizeGenderValue($currentStr) ?? $currentStr;
            }

            if ($requestedValue === $currentStr) {
                continue;
            }

            $normalized[$key] = [
                'label' => self::EDITABLE_FIELDS[$key]['label'],
                'current' => $currentStr !== '' ? $currentStr : null,
                'requested' => $requestedValue,
            ];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'changes' => ['Không có thay đổi nào so với hồ sơ hiện tại.'],
            ]);
        }

        return DashboardProfileChangeRequest::query()->create([
            'user_id' => $user->id,
            'changes' => $normalized,
            'note' => filled($note) ? trim($note) : null,
            'status' => DashboardProfileChangeRequest::STATUS_PENDING,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws HrmDatabaseUnavailable
     */
    private function resolveHrmProfile(User $user): ?array
    {
        if (! HrmEmployeeDirectory::isConfigured()) {
            return null;
        }

        $uuid = trim((string) $user->hrm_employee_uuid);

        return $this->hrmEmployees->findEmployeeForUser(
            $uuid !== '' ? $uuid : null,
            trim((string) $user->email) !== '' ? (string) $user->email : null,
        );
    }

    /** @return array<string, mixed> */
    private function presentWorkspace(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'employee_code' => $user->employee_code,
            'manager_display_name' => $user->manager_display_name,
            'department_name' => $user->department?->name,
            'company_name' => $user->company?->name ?? $user->resolveCompanyName(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $hrm
     * @return list<array{key: string, label: string, value: mixed}>
     */
    private function editableFieldMeta(?array $hrm): array
    {
        $items = [];
        foreach (self::EDITABLE_FIELDS as $key => $meta) {
            $items[] = [
                'key' => $key,
                'label' => $meta['label'],
                'type' => $meta['type'],
                'placeholder' => $meta['placeholder'] ?? null,
                'options' => $meta['options'] ?? null,
                'value' => $hrm[$key] ?? null,
            ];
        }

        return $items;
    }

    private function normalizeGenderValue(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $key = strtolower(trim($value));
        if (in_array($key, ['male', 'm', 'nam'], true)) {
            return 'male';
        }
        if (in_array($key, ['female', 'f', 'nữ', 'nu'], true)) {
            return 'female';
        }

        return $key;
    }

    /** @return list<array<string, mixed>> */
    private function pendingRequestsFor(User $user): array
    {
        return DashboardProfileChangeRequest::query()
            ->where('user_id', $user->id)
            ->where('status', DashboardProfileChangeRequest::STATUS_PENDING)
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (DashboardProfileChangeRequest $row) => [
                'id' => $row->id,
                'changes' => $row->changes,
                'note' => $row->note,
                'status' => $row->status,
                'created_at' => $row->created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function workspaceAvatarPath(?string $avatarUrl): ?string
    {
        if (! filled($avatarUrl)) {
            return null;
        }

        $path = parse_url($avatarUrl, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $prefix = '/storage/';
        if (! str_starts_with($path, $prefix)) {
            return null;
        }

        return ltrim(substr($path, strlen($prefix)), '/');
    }
}
