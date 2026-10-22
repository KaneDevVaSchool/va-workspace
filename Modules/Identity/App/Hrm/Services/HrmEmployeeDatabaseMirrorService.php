<?php

namespace Modules\Identity\App\Hrm\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Identity\App\Hrm\Exceptions\HrmDatabaseUnavailable;
use Modules\Identity\App\Models\Company;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;

/**
 * Tạo/cập nhật bản ghi users workspace từ MySQL VA-HRM (SSOT) — không gọi HTTP.
 * Dùng cho Social/Chat tìm đồng nghiệp, roster superadmin và danh bạ dashboard.
 * Không ghi đè department_id / roles (giống HrmEmployeeSyncService).
 */
class HrmEmployeeDatabaseMirrorService
{
    /** @var list<string> */
    private const ACTIVE_HRM_STATUSES = ['active', 'processing', 'pending_confirmation', 'on_leave'];

    public function __construct(
        private readonly HrmEmployeeDirectory $directory,
        private readonly HrmDepartmentSyncService $departments,
        private readonly UserRepositoryInterface $users,
    ) {}

    public static function isConfigured(): bool
    {
        return HrmEmployeeDirectory::isConfigured();
    }

    /** Bỏ qua nếu chưa cấu HRM_DB_* hoặc đã mirror trong TTL. */
    public function mirrorIfStale(): void
    {
        if (! self::isConfigured()) {
            return;
        }

        $ttlSeconds = max(60, (int) config('services.hrm.employee_bulk_sync_ttl', 900));
        $doneKey = 'hrm:employee_db_mirror:done';

        if (Cache::has($doneKey)) {
            return;
        }

        $lock = Cache::lock('hrm:employee_db_mirror:lock', min(600, $ttlSeconds));
        if (! $lock->get()) {
            return;
        }

        try {
            if (Cache::has($doneKey)) {
                return;
            }

            $this->mirrorAll();
            Cache::put($doneKey, true, $ttlSeconds);
        } finally {
            $lock->release();
        }
    }

    /** Mirror toàn bộ nhân sự HRM (bỏ cache TTL). */
    public function mirrorAll(): void
    {
        if (! self::isConfigured()) {
            return;
        }

        $this->departments->syncDepartmentsFromHrmIfStale();

        try {
            $employees = $this->directory->load()['employees'] ?? [];
        } catch (HrmDatabaseUnavailable $e) {
            Log::warning('hrm.employee_db_mirror.failed', ['message' => $e->getMessage()]);

            return;
        }

        foreach ($employees as $employee) {
            if (! is_array($employee) || ! isset($employee['uuid'])) {
                continue;
            }

            $this->mirrorOne($employee);
        }

        Cache::forget('hrm:directory:snapshot:v1');
    }

    /**
     * Tìm trên DB HRM rồi tạo/cập nhật users.id tương ứng. Không đọc danh bạ từ bảng users.
     *
     * @return list<array{id: int, name: string, avatar_url: ?string, department: ?string, company_name: ?string}>
     */
    public function mappedSearch(string $needle, int $limit, ?int $excludeUserId = null): array
    {
        $needle = mb_strtolower(trim($needle));
        $rows = $this->activeEmployees()
            ->filter(function (array $employee) use ($needle): bool {
                if ($needle === '') {
                    return true;
                }

                $haystack = mb_strtolower(implode(' ', array_filter([
                    $employee['full_name'] ?? '',
                    $employee['company_email'] ?? '',
                    $employee['personal_email'] ?? '',
                    $employee['code'] ?? '',
                ])));

                return str_contains($haystack, $needle);
            })
            ->sortBy(fn (array $employee) => mb_strtolower((string) ($employee['full_name'] ?? '')))
            ->values();

        $presented = [];
        foreach ($rows as $employee) {
            $user = $this->mirrorOne($employee);
            if ($user === null || $user->status !== 'active') {
                continue;
            }
            if ($excludeUserId !== null && (int) $user->id === $excludeUserId) {
                continue;
            }

            $presented[] = [
                'id' => $user->id,
                'name' => filled($employee['full_name'] ?? null) ? (string) $employee['full_name'] : $user->name,
                'avatar_url' => filled($employee['avatar_url'] ?? null) ? (string) $employee['avatar_url'] : $user->avatar_url,
                'department' => filled($employee['department_name'] ?? null) ? (string) $employee['department_name'] : null,
                'company_name' => filled($employee['company_name'] ?? null) ? (string) $employee['company_name'] : null,
            ];

            if (count($presented) >= $limit) {
                break;
            }
        }

        return $presented;
    }

    /**
     * Nhân sự một phòng ban workspace = nhân sự HRM thuộc danh mục phòng ban đó.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function mappedByDepartment(int $departmentId, bool $activeOnly = false): \Illuminate\Support\Collection
    {
        $catalogUuid = Department::query()->whereKey($departmentId)->value('hrm_org_unit_uuid');
        if (! filled($catalogUuid)) {
            return collect();
        }

        $rows = $this->employees()->filter(
            fn (array $employee) => (string) ($employee['catalog_department_uuid'] ?? '') === (string) $catalogUuid
        );

        if ($activeOnly) {
            $rows = $rows->filter(fn (array $employee) => $this->workspaceStatus($employee) === 'active');
        }

        return $rows
            ->map(fn (array $employee) => $this->mirrorOne($employee))
            ->filter()
            ->sortBy(fn (User $user) => mb_strtolower($user->name))
            ->values();
    }

    /**
     * @param  list<int>|null  $departmentIds
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function mappedActive(?array $departmentIds = null): \Illuminate\Support\Collection
    {
        $catalogUuids = null;
        if ($departmentIds !== null) {
            $catalogUuids = Department::query()
                ->whereIn('id', $departmentIds)
                ->pluck('hrm_org_unit_uuid')
                ->filter()
                ->map(fn ($uuid) => (string) $uuid)
                ->all();
        }

        return $this->activeEmployees()
            ->filter(function (array $employee) use ($catalogUuids): bool {
                if ($catalogUuids === null) {
                    return true;
                }

                return in_array((string) ($employee['catalog_department_uuid'] ?? ''), $catalogUuids, true);
            })
            ->map(fn (array $employee) => $this->mirrorOne($employee))
            ->filter(fn (?User $user) => $user !== null && $user->status === 'active')
            ->sortBy(fn (User $user) => mb_strtolower($user->name))
            ->values();
    }

    /**
     * Nhân sự HRM chưa khớp danh mục phòng ban workspace.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function mappedWithoutDepartment(): \Illuminate\Support\Collection
    {
        return $this->employees()
            ->filter(fn (array $employee) => ! filled($employee['catalog_department_uuid'] ?? null))
            ->map(fn (array $employee) => $this->mirrorOne($employee))
            ->filter()
            ->sortBy(fn (User $user) => mb_strtolower($user->name))
            ->values();
    }

    /**
     * @param  list<int>  $departmentIds
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function countsByWorkspaceDepartment(array $departmentIds): \Illuminate\Support\Collection
    {
        $departments = Department::query()
            ->whereIn('id', $departmentIds)
            ->get(['id', 'hrm_org_unit_uuid']);

        $employees = $this->employees();

        return $departments->mapWithKeys(function (Department $department) use ($employees) {
            $catalogUuid = (string) ($department->hrm_org_unit_uuid ?? '');
            $count = $catalogUuid === ''
                ? 0
                : $employees->filter(
                    fn (array $employee) => (string) ($employee['catalog_department_uuid'] ?? '') === $catalogUuid
                )->count();

            return [(int) $department->id => $count];
        });
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function employees(): \Illuminate\Support\Collection
    {
        $ttlSeconds = max(60, (int) config('services.hrm.hrm_roster_cache_ttl', 900));

        /** @var list<array<string, mixed>>|null $cached */
        $cached = Cache::get('hrm:directory:snapshot:v1');
        if (is_array($cached)) {
            return collect($cached);
        }

        $this->departments->syncDepartmentsFromHrmIfStale();

        try {
            $rows = $this->directory->load()['employees'] ?? [];
        } catch (HrmDatabaseUnavailable $e) {
            Log::warning('hrm.employee_db_mirror.failed', ['message' => $e->getMessage()]);

            return collect();
        }

        Cache::put('hrm:directory:snapshot:v1', $rows, $ttlSeconds);

        return collect($rows);
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function activeEmployees(): \Illuminate\Support\Collection
    {
        return $this->employees()->filter(
            fn (array $employee) => $this->workspaceStatus($employee) === 'active'
        );
    }

    /** @param array<string, mixed> $employee Shape từ HrmEmployeeDirectory::presentEmployee */
    public function mirrorOne(array $employee): ?User
    {
        $email = $this->email($employee);
        if ($email === null) {
            return null;
        }

        $employeeUuid = (string) $employee['uuid'];
        $user = $this->users->findByHrmEmployeeUuid($employeeUuid)
            ?? $this->users->findByEmail($email);

        if ($user !== null && filled($user->hrm_employee_uuid) && $user->hrm_employee_uuid !== $employeeUuid) {
            Log::warning('hrm.employee_db_mirror.email_conflict', [
                'employee_uuid' => $employeeUuid,
                'email' => $email,
                'existing_hrm_uuid' => $user->hrm_employee_uuid,
            ]);

            return null;
        }

        $attributes = $this->displayAttributes($employee);

        if ($user === null) {
            $draft = new User();

            return $this->users->create(array_merge([
                'name' => filled($employee['full_name']) ? (string) $employee['full_name'] : $email,
                'email' => $email,
                'hrm_employee_uuid' => $employeeUuid,
                'status' => $this->workspaceStatus($employee),
                'email_verified_at' => now(),
            ], $attributes, $this->departmentAttributeFor($draft, $employee)));
        }

        if ($user->hrm_employee_uuid === null) {
            $attributes['hrm_employee_uuid'] = $employeeUuid;
        }

        $attributes['status'] = $this->workspaceStatus($employee);

        return $this->users->update($user, array_merge($attributes, $this->departmentAttributeFor($user, $employee)));
    }

    /** @param array<string, mixed> $employee */
    private function displayAttributes(array $employee): array
    {
        $attributes = [
            'employee_code' => $employee['code'] ?? null,
            'job_title_name' => $employee['job_title'] ?? null,
            'job_position_level' => $employee['level_name'] ?? null,
            'manager_display_name' => $employee['manager_name'] ?? null,
            'hrm_synced_at' => now(),
        ];

        if (filled($employee['full_name'])) {
            $attributes['name'] = (string) $employee['full_name'];
        }

        if (filled($employee['avatar_url'] ?? null)) {
            $attributes['avatar_url'] = (string) $employee['avatar_url'];
        }

        if (filled($employee['company_uuid'] ?? null)) {
            $company = Company::upsertFromHrm(
                (string) $employee['company_uuid'],
                isset($employee['company_code']) ? (string) $employee['company_code'] : null,
                isset($employee['company_name']) ? (string) $employee['company_name'] : null,
            );
            $attributes['company_id'] = $company->id;
        }

        return $attributes;
    }

    /**
     * Gán department_id lần đầu theo danh mục HRM. Không ghi đè phòng ban đã gán tay.
     *
     * @param  array<string, mixed>  $employee
     * @return array<string, mixed>
     */
    private function departmentAttributeFor(User $user, array $employee): array
    {
        if ($user->exists && $user->department_id !== null) {
            return [];
        }

        $catalogUuid = isset($employee['catalog_department_uuid'])
            ? (string) $employee['catalog_department_uuid']
            : '';
        if ($catalogUuid === '') {
            return [];
        }

        $departmentId = Department::query()
            ->where('hrm_org_unit_uuid', $catalogUuid)
            ->where('is_active', true)
            ->value('id');

        return $departmentId !== null ? ['department_id' => (int) $departmentId] : [];
    }

    /** @param array<string, mixed> $employee */
    private function workspaceStatus(array $employee): string
    {
        $hrmStatus = (string) ($employee['status'] ?? 'active');

        return in_array($hrmStatus, self::ACTIVE_HRM_STATUSES, true) ? 'active' : 'inactive';
    }

    /** @param array<string, mixed> $employee */
    private function email(array $employee): ?string
    {
        $email = $employee['company_email'] ?? $employee['personal_email'] ?? null;

        return is_string($email) && $email !== '' ? strtolower($email) : null;
    }
}
