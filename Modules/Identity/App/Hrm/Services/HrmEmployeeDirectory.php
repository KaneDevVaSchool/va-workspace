<?php

namespace Modules\Identity\App\Hrm\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Identity\App\Hrm\Exceptions\HrmDatabaseUnavailable;
use Throwable;

/**
 * Đọc nhân sự VA-HRM thẳng từ MySQL (bảng employees / assignments / org_units).
 * Không gọi HTTP API. Dùng cho trang Nhân sự workspace.
 */
class HrmEmployeeDirectory
{
    /** @var list<string> */
    private const ASSIGNABLE_ORG_UNIT_TYPES = ['department', 'unit', 'branch'];

    public static function isConfigured(): bool
    {
        return filled(config('database.connections.hrm.database'));
    }

    /**
     * @return array{employees: list<array<string, mixed>>, org_units: list<array<string, mixed>>}
     *
     * @throws HrmDatabaseUnavailable
     */
    public function load(): array
    {
        try {
            $orgUnits = $this->orgUnits();
            $unitsById = $orgUnits->keyBy('id');
            $concurrent = $this->concurrentByEmployeeId();
            $names = $this->employeeNames();
            $avatars = $this->avatarsByEmployeeId();

            $employees = $this->employeeRows()
                ->map(fn (object $row) => $this->presentEmployee($row, $unitsById, $names, $concurrent, $avatars))
                ->values()
                ->all();

            return [
                'employees' => $employees,
                'org_units' => $orgUnits
                    ->filter(fn (object $unit) => in_array((string) $unit->type, self::ASSIGNABLE_ORG_UNIT_TYPES, true))
                    ->map(fn (object $unit) => $this->presentOrgUnit($unit))
                    ->values()
                    ->all(),
            ];
        } catch (HrmDatabaseUnavailable $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('hrm.database.roster_failed', ['message' => $e->getMessage()]);

            throw new HrmDatabaseUnavailable('Không đọc được danh sách nhân sự từ cơ sở dữ liệu HRM.');
        }
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws HrmDatabaseUnavailable
     */
    /**
     * Phòng ban của phân công chính: org unit type department, hoặc cha của bộ phận.
     *
     * @return array<string, mixed>|null
     *
     * @throws HrmDatabaseUnavailable
     */
    public function primaryDepartmentOrgUnit(?string $employeeUuid, ?string $email): ?array
    {
        if (($employeeUuid === null || $employeeUuid === '') && ($email === null || $email === '')) {
            return null;
        }

        try {
            $query = DB::connection('hrm')
                ->table('employees as e')
                ->leftJoin('employee_assignments as a', 'a.open_primary_employee_id', '=', 'e.id')
                ->whereNull('e.deleted_at');

            if ($employeeUuid !== null && $employeeUuid !== '') {
                $query->where('e.uuid', $employeeUuid);
            } else {
                $query->whereRaw('LOWER(e.company_email) = ?', [strtolower((string) $email)]);
            }

            $orgUnitId = $query->value('a.org_unit_id');
            if (! $orgUnitId) {
                return null;
            }

            $unit = $this->departmentAncestor($orgUnitId);

            return $unit !== null ? $this->presentOrgUnit($unit) : null;
        } catch (Throwable $e) {
            Log::warning('hrm.database.department_lookup_failed', ['message' => $e->getMessage()]);

            throw new HrmDatabaseUnavailable('Không đọc được phòng ban nhân sự từ cơ sở dữ liệu HRM.');
        }
    }

    public function findEmployee(string $uuid): ?array
    {
        foreach ($this->load()['employees'] as $employee) {
            if (($employee['uuid'] ?? '') === $uuid) {
                return $employee;
            }
        }

        return null;
    }

    private function employeeRows(): Collection
    {
        return DB::connection('hrm')
            ->table('employees as e')
            ->leftJoin('employee_assignments as a', 'a.open_primary_employee_id', '=', 'e.id')
            ->leftJoin('companies as c', 'c.id', '=', 'a.company_id')
            ->leftJoin('org_units as ou', 'ou.id', '=', 'a.org_unit_id')
            ->leftJoin('positions as p', 'p.id', '=', 'a.position_id')
            ->leftJoin('job_titles as jt', 'jt.id', '=', 'p.job_title_id')
            ->leftJoin('position_levels as pl', 'pl.level', '=', 'p.level')
            ->whereNull('e.deleted_at')
            ->orderBy('e.full_name')
            ->get([
                'e.id',
                'e.uuid',
                'e.code',
                'e.full_name',
                'e.company_email',
                'e.personal_email',
                'e.phone',
                'e.gender',
                'e.birthdate',
                'e.personnel_type',
                'e.workplace',
                'e.attendance_code',
                'e.hired_at',
                'e.actual_start_date',
                'e.employment_status',
                'e.created_at',
                'e.department_name as profile_department_name',
                'e.avatar_path',
                'e.status',
                'e.direct_manager_name',
                'e.job_title_name as profile_job_title',
                'c.uuid as company_uuid',
                'c.code as company_code',
                'c.name as company_name',
                'ou.id as org_unit_id',
                'ou.uuid as org_unit_uuid',
                'ou.code as org_unit_code',
                'ou.name as org_unit_name',
                'ou.path as org_unit_path',
                'p.title as position_title',
                'p.level as position_level',
                'jt.name as job_title',
                'pl.name as level_name',
            ]);
    }

    private function concurrentByEmployeeId(): Collection
    {
        return DB::connection('hrm')
            ->table('employee_assignments as a')
            ->leftJoin('companies as c', 'c.id', '=', 'a.company_id')
            ->leftJoin('org_units as ou', 'ou.id', '=', 'a.org_unit_id')
            ->leftJoin('positions as p', 'p.id', '=', 'a.position_id')
            ->leftJoin('job_titles as jt', 'jt.id', '=', 'p.job_title_id')
            ->where('a.is_primary', 0)
            ->whereNull('a.effective_to')
            ->get([
                'a.employee_id',
                'a.org_unit_id',
                'jt.name as job_title',
                'p.title as position_title',
                'c.name as company_name',
                'ou.name as org_unit_name',
            ])
            ->groupBy('employee_id');
    }

    private function orgUnits(): Collection
    {
        return DB::connection('hrm')
            ->table('org_units as ou')
            ->leftJoin('companies as c', 'c.id', '=', 'ou.company_id')
            ->whereNull('ou.deleted_at')
            ->get([
                'ou.id',
                'ou.uuid',
                'ou.parent_id',
                'ou.type',
                'ou.code',
                'ou.name',
                'ou.short_name',
                'ou.status',
                'ou.manager_employee_id',
                'c.uuid as company_uuid',
                'c.code as company_code',
                'c.name as company_name',
            ]);
    }

    /** @return Collection<int|string, string|null> */
    private function avatarsByEmployeeId(): Collection
    {
        return DB::connection('hrm')
            ->table('users')
            ->whereNull('deleted_at')
            ->whereNotNull('employee_id')
            ->pluck('avatar_url', 'employee_id');
    }

    /** @return Collection<int|string, string> */
    private function employeeNames(): Collection
    {
        return DB::connection('hrm')
            ->table('employees')
            ->whereNull('deleted_at')
            ->pluck('full_name', 'id');
    }

    /**
     * @param  Collection<int|string, object>  $unitsById
     * @param  Collection<int|string, string>  $names
     * @param  Collection<int|string, Collection<int, object>>  $concurrent
     * @param  Collection<int|string, string|null>  $avatars
     * @return array<string, mixed>
     */
    private function presentEmployee(object $row, Collection $unitsById, Collection $names, Collection $concurrent, Collection $avatars): array
    {
        $jobTitle = $row->job_title ?: $row->position_title ?: $row->profile_job_title;
        $level = filled($row->level_name)
            ? (string) $row->level_name
            : ($row->position_level !== null && $row->position_level !== '' ? (string) $row->position_level : null);
        $concurrentRows = $concurrent->get($row->id) ?? $concurrent->get((string) $row->id) ?? collect();
        $placement = $this->placementNames($row->org_unit_id, $unitsById);
        $accountAvatar = $avatars->get($row->id) ?? $avatars->get((string) $row->id) ?? $avatars->get((int) $row->id);

        return [
            'uuid' => (string) $row->uuid,
            'code' => filled($row->code) ? (string) $row->code : null,
            'full_name' => (string) ($row->full_name ?? ''),
            'company_email' => filled($row->company_email) ? (string) $row->company_email : null,
            'personal_email' => filled($row->personal_email) ? (string) $row->personal_email : null,
            'phone' => filled($row->phone) ? (string) $row->phone : null,
            'gender' => filled($row->gender) ? (string) $row->gender : null,
            'birthdate' => filled($row->birthdate) ? (string) $row->birthdate : null,
            'personnel_type' => filled($row->personnel_type) ? (string) $row->personnel_type : null,
            'workplace' => filled($row->workplace) ? (string) $row->workplace : null,
            'attendance_code' => filled($row->attendance_code) ? (string) $row->attendance_code : null,
            'hired_at' => filled($row->hired_at) ? (string) $row->hired_at : null,
            'actual_start_date' => filled($row->actual_start_date) ? (string) $row->actual_start_date : null,
            'employment_status' => filled($row->employment_status) ? (string) $row->employment_status : null,
            'created_at' => filled($row->created_at) ? (string) $row->created_at : null,
            'profile_department_name' => filled($row->profile_department_name) ? (string) $row->profile_department_name : null,
            'status' => (string) ($row->status ?? 'active'),
            'job_title' => filled($jobTitle) ? (string) $jobTitle : null,
            'level_name' => $level,
            'company_uuid' => filled($row->company_uuid) ? (string) $row->company_uuid : null,
            'company_code' => filled($row->company_code) ? (string) $row->company_code : null,
            'company_name' => filled($row->company_name) ? (string) $row->company_name : null,
            'org_unit_uuid' => filled($row->org_unit_uuid) ? (string) $row->org_unit_uuid : null,
            'org_unit_code' => filled($row->org_unit_code) ? (string) $row->org_unit_code : null,
            'org_unit_name' => filled($row->org_unit_name) ? (string) $row->org_unit_name : null,
            'org_unit_path' => filled($row->org_unit_path) ? (string) $row->org_unit_path : null,
            'department_name' => $placement['department_name'],
            'division_name' => $placement['division_name'],
            'avatar_url' => $this->avatarUrl($row->avatar_path ?? null, is_string($accountAvatar) ? $accountAvatar : null),
            'manager_name' => $this->managerName($row, $unitsById, $names),
            'concurrent_positions' => $concurrentRows
                ->map(function (object $item) use ($unitsById) {
                    $placement = $this->placementNames($item->org_unit_id ?? null, $unitsById);

                    return [
                        'job_title_name' => $item->job_title ?: $item->position_title,
                        'company_name' => $item->company_name,
                        'org_unit_name' => $item->org_unit_name,
                        'department_name' => $placement['department_name'],
                        'division_name' => $placement['division_name'],
                    ];
                })
                ->filter(fn (array $item) => filled($item['job_title_name']) || filled($item['company_name']) || filled($item['org_unit_name']) || filled($item['department_name']) || filled($item['division_name']))
                ->values()
                ->all(),
        ];
    }

    /**
     * Phân công trỏ tới nút lá. Phòng ban là org unit type department
     * (chính nút đó hoặc cha). Bộ phận là org unit type unit.
     *
     * @param  Collection<int|string, object>  $unitsById
     * @return array{department_name: ?string, division_name: ?string}
     */
    private function placementNames(mixed $orgUnitId, Collection $unitsById): array
    {
        $department = null;
        $division = null;
        $current = $orgUnitId;
        $guard = 0;

        while ($current && $guard++ < 12) {
            $unit = $unitsById->get($current) ?? $unitsById->get((int) $current) ?? $unitsById->get((string) $current);
            if ($unit === null) {
                break;
            }

            $type = (string) ($unit->type ?? '');
            if ($type === 'unit' && $division === null && filled($unit->name)) {
                $division = (string) $unit->name;
            }
            if ($type === 'department' && filled($unit->name)) {
                $department = (string) $unit->name;
                break;
            }

            $current = $unit->parent_id;
        }

        return [
            'department_name' => $department,
            'division_name' => $division,
        ];
    }

    private function avatarUrl(mixed $path, ?string $accountUrl): ?string
    {
        $stored = trim((string) $path);
        if ($stored !== '') {
            if (preg_match('#^https?://#i', $stored) === 1) {
                return $stored;
            }

            $base = rtrim((string) config('services.hrm.api_base_url'), '/');
            if ($base !== '') {
                $relative = ltrim($stored, '/');
                if (! str_starts_with($relative, 'storage/')) {
                    $relative = 'storage/'.$relative;
                }

                return $base.'/'.$relative;
            }
        }

        $account = trim((string) $accountUrl);

        return $account !== '' ? $account : null;
    }

    /**
     * @param  Collection<int|string, object>  $unitsById
     * @param  Collection<int|string, string>  $names
     */
    private function managerName(object $row, Collection $unitsById, Collection $names): ?string
    {
        $direct = trim((string) ($row->direct_manager_name ?? ''));
        if ($direct !== '') {
            return $direct;
        }

        $unitId = $row->org_unit_id;
        $guard = 0;

        while ($unitId && $guard++ < 12) {
            $unit = $unitsById->get($unitId) ?? $unitsById->get((int) $unitId);
            if ($unit === null) {
                break;
            }

            $managerId = $unit->manager_employee_id;
            if ($managerId && (int) $managerId !== (int) $row->id) {
                $name = $names->get($managerId) ?? $names->get((int) $managerId);
                if (is_string($name) && trim($name) !== '') {
                    return trim($name);
                }
            }

            $unitId = $unit->parent_id;
        }

        return null;
    }

    private function departmentAncestor(mixed $orgUnitId): ?object
    {
        $current = $orgUnitId;
        $guard = 0;

        while ($current && $guard++ < 12) {
            $unit = DB::connection('hrm')
                ->table('org_units as ou')
                ->leftJoin('companies as c', 'c.id', '=', 'ou.company_id')
                ->where('ou.id', $current)
                ->whereNull('ou.deleted_at')
                ->first([
                    'ou.id',
                    'ou.uuid',
                    'ou.parent_id',
                    'ou.type',
                    'ou.code',
                    'ou.name',
                    'ou.short_name',
                    'ou.status',
                    'ou.manager_employee_id',
                    'c.uuid as company_uuid',
                    'c.code as company_code',
                    'c.name as company_name',
                ]);

            if ($unit === null) {
                return null;
            }

            if ((string) $unit->type === 'department') {
                return $unit;
            }

            $current = $unit->parent_id;
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function presentOrgUnit(object $unit): array
    {
        return [
            'uuid' => (string) $unit->uuid,
            'code' => $unit->code,
            'name' => $unit->name,
            'short_name' => $unit->short_name,
            'type' => $unit->type,
            'status' => $unit->status,
            'company' => filled($unit->company_uuid) ? [
                'uuid' => (string) $unit->company_uuid,
                'code' => $unit->company_code,
                'name' => $unit->company_name,
            ] : null,
        ];
    }
}
