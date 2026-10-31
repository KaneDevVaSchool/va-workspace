<?php

namespace Modules\Identity\App\Hrm\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Identity\App\Hrm\Exceptions\HrmDatabaseUnavailable;
use Modules\Identity\App\Models\Company;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;
use Modules\Identity\App\Support\UserAvatarUrl;
use Throwable;

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
     * @return list<array{id: int, name: string, avatar_url: ?string, department: ?string, job_title_name: ?string, company_name: ?string}>
     */
    public function mappedSearch(string $needle, int $limit, ?int $excludeUserId = null): array
    {
        $presented = [];
        foreach ($this->searchRows($needle) as $employee) {
            $row = $this->presentSearchRow($employee, $excludeUserId);
            if ($row === null) {
                continue;
            }

            $presented[] = $row;
            if (count($presented) >= $limit) {
                break;
            }
        }

        return $presented;
    }

    /**
     * Một trang kết quả tìm đồng nghiệp (cuộn tải thêm). Cắt trang trên danh sách HRM trước
     * khi mirror để chỉ ghi users cho đúng trang đang xem — trang có chính người tìm ít hơn 1 dòng.
     *
     * @return array{users: list<array{id: int, name: string, avatar_url: ?string, department: ?string, job_title_name: ?string, company_name: ?string}>, has_more: bool}
     */
    public function mappedSearchPage(string $needle, int $limit, int $offset = 0, ?int $excludeUserId = null): array
    {
        $rows = $this->searchRows($needle);

        $users = $rows->slice($offset, $limit)
            ->map(fn (array $employee) => $this->presentSearchRow($employee, $excludeUserId))
            ->filter()
            ->values()
            ->all();

        return [
            'users' => $users,
            'has_more' => $rows->count() > $offset + $limit,
        ];
    }

    /**
     * Không phân biệt dấu/hoa thường, mọi từ gõ vào đều phải khớp (không cần đúng thứ tự):
     * "khoa nguyen" ra "Nguyễn Khoa". Người khớp theo tên đứng trước người chỉ khớp
     * email / mã nhân viên / phòng ban.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function searchRows(string $needle): \Illuminate\Support\Collection
    {
        $tokens = preg_split('/\s+/', self::foldForSearch($needle), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $this->activeEmployees()
            ->filter(fn (array $employee) => $this->email($employee) !== null)
            ->map(function (array $employee) use ($tokens): ?array {
                $name = self::foldForSearch((string) ($employee['full_name'] ?? ''));
                if (self::containsAll($name, $tokens)) {
                    return ['employee' => $employee, 'rank' => 0, 'name' => $name];
                }

                $haystack = $name.' '.self::foldForSearch(implode(' ', array_filter([
                    $employee['company_email'] ?? '',
                    $employee['personal_email'] ?? '',
                    $employee['code'] ?? '',
                    $employee['department_name'] ?? '',
                ])));

                return self::containsAll($haystack, $tokens)
                    ? ['employee' => $employee, 'rank' => 1, 'name' => $name]
                    : null;
            })
            ->filter()
            ->sortBy([['rank', 'asc'], ['name', 'asc']])
            ->pluck('employee')
            ->values();
    }

    /** @param list<string> $tokens */
    private static function containsAll(string $haystack, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (! str_contains($haystack, $token)) {
                return false;
            }
        }

        return true;
    }

    private static function foldForSearch(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }

    /**
     * @param  array<string, mixed>  $employee
     * @return array{id: int, name: string, avatar_url: ?string, department: ?string, job_title_name: ?string, company_name: ?string}|null
     */
    private function presentSearchRow(array $employee, ?int $excludeUserId): ?array
    {
        // Một hồ sơ HRM lỗi (trùng email/uuid, dữ liệu lạ…) chỉ làm người đó vắng mặt, không làm trống cả kết quả tìm.
        try {
            $user = $this->mirrorOne($employee);
        } catch (Throwable $e) {
            Log::warning('hrm.colleague_search.mirror_failed', [
                'employee_uuid' => $employee['uuid'] ?? null,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
        if ($user === null || $user->status !== 'active') {
            return null;
        }
        if ($excludeUserId !== null && (int) $user->id === $excludeUserId) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => filled($employee['full_name'] ?? null) ? (string) $employee['full_name'] : $user->name,
            'avatar_url' => filled($employee['avatar_url'] ?? null) ? (string) $employee['avatar_url'] : $user->avatar_url,
            'department' => filled($employee['department_name'] ?? null) ? (string) $employee['department_name'] : null,
            'job_title_name' => filled($employee['job_title'] ?? null) ? (string) $employee['job_title'] : null,
            'company_name' => filled($employee['company_name'] ?? null) ? (string) $employee['company_name'] : null,
        ];
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

        $attributes = $this->displayAttributes($employee, $user);

        if ($user !== null && filled($user->hrm_employee_uuid) && $user->hrm_employee_uuid !== $employeeUuid) {
            $holder = User::query()
                ->where('hrm_employee_uuid', $employeeUuid)
                ->where('id', '!=', $user->id)
                ->exists();
            if ($holder) {
                Log::warning('hrm.employee_db_mirror.uuid_holder_conflict', [
                    'employee_uuid' => $employeeUuid,
                    'email' => $email,
                    'user_id' => $user->id,
                ]);

                return null;
            }

            // Hai hồ sơ HRM cùng company_email: không đổi uuid đã lưu (thứ tự
            // mirror sẽ nhảy uuid và list đơn nghỉ trỏ nhầm nhân sự).
            if ($this->companyEmailIsShared($email)) {
                Log::warning('hrm.employee_db_mirror.uuid_kept_duplicate_email', [
                    'user_id' => $user->id,
                    'email' => $email,
                    'kept' => $user->hrm_employee_uuid,
                    'skipped' => $employeeUuid,
                ]);

                return $user;
            }

            Log::info('hrm.employee_db_mirror.uuid_reconciled', [
                'user_id' => $user->id,
                'email' => $email,
                'from' => $user->hrm_employee_uuid,
                'to' => $employeeUuid,
            ]);
            $attributes['hrm_employee_uuid'] = $employeeUuid;
        }

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
    private function displayAttributes(array $employee, ?User $user = null): array
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

        if (
            filled($employee['avatar_url'] ?? null)
            && ($user === null || ! UserAvatarUrl::isUserUploaded($user->avatar_url))
        ) {
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

    /**
     * Nhiều dòng employees HRM dùng chung một company_email.
     */
    private function companyEmailIsShared(string $email): bool
    {
        if (! HrmEmployeeDirectory::isConfigured()) {
            return false;
        }

        try {
            $count = DB::connection('hrm')
                ->table('employees')
                ->whereRaw('LOWER(company_email) = ?', [strtolower($email)])
                ->count();

            return $count > 1;
        } catch (Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $employee */
    private function email(array $employee): ?string
    {
        $email = $employee['company_email'] ?? $employee['personal_email'] ?? null;

        return is_string($email) && $email !== '' ? strtolower($email) : null;
    }
}
