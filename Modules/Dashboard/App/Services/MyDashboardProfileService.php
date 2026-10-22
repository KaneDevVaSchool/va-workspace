<?php

namespace Modules\Dashboard\App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Dashboard\App\Models\DashboardProfileChangeRequest;
use Modules\Identity\App\Hrm\Exceptions\HrmDatabaseUnavailable;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;

class MyDashboardProfileService
{
    /** @var array<string, string> */
    public const EDITABLE_FIELD_LABELS = [
        'phone' => 'Số điện thoại',
        'personal_email' => 'Email cá nhân',
        'birthdate' => 'Ngày sinh',
        'gender' => 'Giới tính',
        'workplace' => 'Nơi làm việc',
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
            if (! array_key_exists($key, self::EDITABLE_FIELD_LABELS)) {
                continue;
            }

            $requestedValue = is_string($requested) ? trim($requested) : '';
            if ($requestedValue === '') {
                continue;
            }

            $current = $hrm !== null ? ($hrm[$key] ?? null) : null;
            $currentStr = is_string($current) ? trim($current) : (filled($current) ? (string) $current : '');

            if ($requestedValue === $currentStr) {
                continue;
            }

            $normalized[$key] = [
                'label' => self::EDITABLE_FIELD_LABELS[$key],
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
        foreach (self::EDITABLE_FIELD_LABELS as $key => $label) {
            $items[] = [
                'key' => $key,
                'label' => $label,
                'value' => $hrm[$key] ?? null,
            ];
        }

        return $items;
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
