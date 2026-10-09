<?php

namespace Modules\Identity\App\Hrm\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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

    /** @var Collection<int|string, object>|null */
    private ?Collection $orgUnitsCache = null;

    /**
     * @var array{
     *   by_id: Collection<int|string, object>,
     *   by_company_code: array<string, object>,
     *   by_company_name: array<string, object>
     * }|null
     */
    private ?array $hrmCatalogDepartments = null;

    /** @var array<string, object>|null division index: "{department_id}|{key}" */
    private ?array $hrmDivisionsByDeptKey = null;

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
            $match = null;
            if ($employeeUuid !== null && $employeeUuid !== '') {
                $match = $this->primaryAssignmentQuery()
                    ->where('e.uuid', $employeeUuid)
                    ->first(['a.org_unit_id', 'e.uuid as employee_uuid']);
            }

            if (($match === null || ! $match->org_unit_id) && $email !== null && $email !== '') {
                $query = $this->primaryAssignmentQuery();
                $column = $query->getGrammar()->wrap('e.company_email');
                $match = $query
                    ->whereRaw('LOWER('.$column.') = ?', [strtolower($email)])
                    ->first(['a.org_unit_id', 'e.uuid as employee_uuid']);
            }
            if ($match === null || ! $match->org_unit_id) {
                return null;
            }

            $unit = $this->departmentAncestor($match->org_unit_id);
            if ($unit === null) {
                return null;
            }

            $presented = $this->presentOrgUnit($unit);
            $presented['employee_uuid'] = (string) $match->employee_uuid;

            return $presented;
        } catch (Throwable $e) {
            Log::warning('hrm.database.department_lookup_failed', ['message' => $e->getMessage()]);

            throw new HrmDatabaseUnavailable('Không đọc được phòng ban nhân sự từ cơ sở dữ liệu HRM.');
        }
    }

    /**
     * Org unit gán được nhân sự, đọc từ MySQL. Kèm trưởng đơn vị nếu có.
     *
     * @return list<array<string, mixed>>
     *
     * @throws HrmDatabaseUnavailable
     */
    /**
     * Danh mục phòng ban HRM — trang Tổ chức → Phòng ban (`/organization/departments`).
     *
     * @return list<array<string, mixed>>
     *
     * @throws HrmDatabaseUnavailable
     */
    public function organizationDepartments(): array
    {
        try {
            return DB::connection('hrm')
                ->table('departments as d')
                ->join('companies as c', 'c.id', '=', 'd.company_id')
                ->leftJoin('employees as m', function ($join): void {
                    $join->on('m.id', '=', 'd.manager_employee_id')
                        ->whereNull('m.deleted_at');
                })
                ->whereNull('d.deleted_at')
                ->orderBy('c.code')
                ->orderBy('d.name')
                ->get([
                    'd.uuid',
                    'd.code',
                    'd.software_code',
                    'd.name',
                    'd.status',
                    'c.uuid as company_uuid',
                    'c.code as company_code',
                    'c.name as company_name',
                    'm.uuid as manager_uuid',
                    'm.full_name as manager_name',
                    'm.company_email as manager_email',
                ])
                ->map(function (object $row): array {
                    return [
                        'uuid' => (string) $row->uuid,
                        'code' => $row->code,
                        'software_code' => filled($row->software_code) ? (string) $row->software_code : null,
                        'name' => $row->name,
                        'status' => (string) ($row->status ?? 'active'),
                        'company' => [
                            'uuid' => (string) $row->company_uuid,
                            'code' => $row->company_code,
                            'name' => $row->company_name,
                        ],
                        'manager' => filled($row->manager_uuid) ? [
                            'uuid' => (string) $row->manager_uuid,
                            'full_name' => (string) ($row->manager_name ?? ''),
                            'email' => filled($row->manager_email) ? (string) $row->manager_email : null,
                        ] : null,
                    ];
                })
                ->values()
                ->all();
        } catch (HrmDatabaseUnavailable $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('hrm.database.organization_departments_failed', ['message' => $e->getMessage()]);

            throw new HrmDatabaseUnavailable('Không đọc được danh mục phòng ban từ cơ sở dữ liệu HRM.');
        }
    }

    /**
     * UUID phòng ban danh mục HRM của nhân sự (từ phân công org unit → khớp danh mục).
     */
    public function catalogDepartmentUuidForEmployee(?string $employeeUuid, ?string $email): ?string
    {
        try {
            $presented = $this->primaryDepartmentOrgUnit($employeeUuid, $email);
            if ($presented === null || ! filled($presented['uuid'] ?? null)) {
                return null;
            }

            $unit = $this->orgUnits()->first(fn (object $row): bool => (string) $row->uuid === (string) $presented['uuid']);
            if ($unit === null) {
                return null;
            }

            $catalog = $this->linkedCatalogDepartment($unit);
            if ($catalog === null || ! filled($catalog->uuid ?? null)) {
                return null;
            }

            return (string) $catalog->uuid;
        } catch (HrmDatabaseUnavailable $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('hrm.database.catalog_department_lookup_failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    public function assignableOrgUnits(): array
    {
        try {
            $units = $this->orgUnits();
            $managerIds = $units->pluck('manager_employee_id')->filter()->unique()->values();
            $managers = $managerIds->isEmpty()
                ? collect()
                : DB::connection('hrm')
                    ->table('employees')
                    ->whereIn('id', $managerIds->all())
                    ->whereNull('deleted_at')
                    ->get(['id', 'uuid', 'full_name', 'company_email'])
                    ->keyBy('id');

            return $units
                ->filter(fn (object $unit) => in_array((string) $unit->type, self::ASSIGNABLE_ORG_UNIT_TYPES, true))
                ->map(function (object $unit) use ($managers) {
                    $presented = $this->presentOrgUnit($unit);
                    $manager = $managers->get($unit->manager_employee_id)
                        ?? $managers->get((int) $unit->manager_employee_id);
                    $presented['manager'] = $manager === null ? null : [
                        'uuid' => (string) $manager->uuid,
                        'full_name' => (string) ($manager->full_name ?? ''),
                        'email' => filled($manager->company_email) ? (string) $manager->company_email : null,
                    ];

                    return $presented;
                })
                ->values()
                ->all();
        } catch (HrmDatabaseUnavailable $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('hrm.database.org_units_failed', ['message' => $e->getMessage()]);

            throw new HrmDatabaseUnavailable('Không đọc được đơn vị tổ chức từ cơ sở dữ liệu HRM.');
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

    /**
     * Trưởng phòng ban của nhân sự — org unit type department (leo cây từ phân công).
     *
     * @return array{uuid: string, full_name: string, job_title: ?string, department_name: ?string, source: string}|null
     *
     * @throws HrmDatabaseUnavailable
     */
    public function resolveDepartmentHeadForEmployee(string $employeeUuid): ?array
    {
        $row = $this->employeeRows()->first(
            fn (object $item): bool => (string) ($item->uuid ?? '') === $employeeUuid,
        );

        if ($row === null) {
            return null;
        }

        $unitsById = $this->orgUnitsById();
        $departmentUnit = $this->departmentAncestor($row->org_unit_id);
        if ($departmentUnit === null) {
            return null;
        }

        $managerId = $departmentUnit->manager_employee_id ?? null;
        if ($managerId === null || (int) $managerId === (int) $row->id) {
            return null;
        }

        $manager = DB::connection('hrm')
            ->table('employees')
            ->where('id', $managerId)
            ->whereNull('deleted_at')
            ->first(['uuid', 'full_name', 'status']);

        if ($manager === null || ! filled($manager->uuid)) {
            return null;
        }

        $status = (string) ($manager->status ?? 'active');
        if (in_array($status, ['terminated', 'inactive'], true)) {
            return null;
        }

        return [
            'uuid' => (string) $manager->uuid,
            'full_name' => trim((string) ($manager->full_name ?? '')),
            'job_title' => null,
            'department_name' => filled($departmentUnit->name) ? (string) $departmentUnit->name : null,
            'source' => 'department_head',
        ];
    }

    /**
     * Nhân sự phụ trách hồ sơ (portal HRM — mục «Nhân sự phụ trách» trên hồ sơ nhân viên).
     *
     * @return array{uuid: ?string, full_name: string, code: ?string, job_title: ?string}|null
     *
     * @throws HrmDatabaseUnavailable
     */
    /**
     * Cấp trên trực tiếp trên hồ sơ — cột direct_manager_name (vd. Nguyễn Viết Hùng (VA010067)).
     *
     * @return array{uuid: ?string, full_name: string, code: ?string, job_title: ?string}|null
     *
     * @throws HrmDatabaseUnavailable
     */
    public function resolveDirectManagerFromProfile(string $employeeUuid): ?array
    {
        $row = DB::connection('hrm')
            ->table('employees')
            ->where('uuid', $employeeUuid)
            ->whereNull('deleted_at')
            ->first(['direct_manager_name']);

        if ($row === null || ! filled($row->direct_manager_name ?? null)) {
            return null;
        }

        return $this->personFromManagerLabel((string) $row->direct_manager_name);
    }

    /**
     * @return array{uuid: ?string, full_name: string, code: ?string, job_title: ?string}|null
     */
    private function personFromManagerLabel(string $label): ?array
    {
        $label = trim($label);
        if ($label === '') {
            return null;
        }

        $code = $this->extractEmployeeCodeFromLabel($label);
        if ($code !== null) {
            $match = DB::connection('hrm')
                ->table('employees')
                ->whereNull('deleted_at')
                ->where('code', $code)
                ->first(['uuid', 'full_name', 'code', 'job_title_name', 'status']);

            if ($match !== null && $this->isActiveHrmEmployee($match)) {
                return [
                    'uuid' => filled($match->uuid ?? null) ? (string) $match->uuid : null,
                    'full_name' => trim((string) ($match->full_name ?? '')),
                    'code' => (string) ($match->code ?? $code),
                    'job_title' => filled($match->job_title_name ?? null) ? (string) $match->job_title_name : null,
                ];
            }
        }

        $name = trim((string) preg_replace('/\s*\([^)]*\)\s*$/u', '', $label));
        if ($name === '') {
            return null;
        }

        return [
            'uuid' => null,
            'full_name' => $name,
            'code' => $code,
            'job_title' => null,
        ];
    }

    private function extractEmployeeCodeFromLabel(string $label): ?string
    {
        if (preg_match('/\(([A-Za-z]{2}\d+)\)\s*$/u', trim($label), $matches)) {
            return strtoupper($matches[1]);
        }

        if (preg_match('/\(([A-Za-z0-9_-]+)\)\s*$/u', trim($label), $matches)) {
            return strtoupper(trim($matches[1]));
        }

        return null;
    }

    public function resolveHrPersonInChargeForEmployee(string $employeeUuid): ?array
    {
        $employee = DB::connection('hrm')
            ->table('employees')
            ->where('uuid', $employeeUuid)
            ->whereNull('deleted_at')
            ->first();

        if ($employee === null) {
            return null;
        }

        $fkColumn = $this->hrInChargeEmployeeIdColumn();
        if ($fkColumn !== null && filled($employee->{$fkColumn} ?? null)) {
            $hrRow = DB::connection('hrm')
                ->table('employees')
                ->where('id', $employee->{$fkColumn})
                ->whereNull('deleted_at')
                ->first(['uuid', 'full_name', 'code', 'job_title_name', 'status']);

            if ($hrRow !== null && $this->isActiveHrmEmployee($hrRow)) {
                return $this->presentHrInChargeRow($hrRow);
            }
        }

        $name = $this->firstFilledColumn($employee, [
            'hr_responsible_name',
            'hr_officer_name',
            'hr_person_in_charge_name',
            'assigned_hr_name',
        ]);
        if ($name !== null) {
            $code = $this->firstFilledColumn($employee, [
                'hr_responsible_code',
                'hr_officer_code',
                'assigned_hr_code',
            ]);

            return [
                'uuid' => null,
                'full_name' => $name,
                'code' => $code,
                'job_title' => null,
            ];
        }

        return null;
    }

    private function hrInChargeEmployeeIdColumn(): ?string
    {
        try {
            $schema = Schema::connection('hrm');
            foreach ([
                'hr_owner_employee_id',
                'hr_responsible_employee_id',
                'assigned_hr_employee_id',
                'hr_officer_employee_id',
                'hr_in_charge_employee_id',
                'hr_pic_employee_id',
            ] as $column) {
                if ($schema->hasColumn('employees', $column)) {
                    return $column;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @param  list<string>  $columns
     */
    private function firstFilledColumn(object $row, array $columns): ?string
    {
        try {
            $schema = Schema::connection('hrm');
            foreach ($columns as $column) {
                if (! $schema->hasColumn('employees', $column)) {
                    continue;
                }
                $value = trim((string) ($row->{$column} ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /** @return array{uuid: ?string, full_name: string, code: ?string, job_title: ?string} */
    private function presentHrInChargeRow(object $row): array
    {
        return [
            'uuid' => filled($row->uuid ?? null) ? (string) $row->uuid : null,
            'full_name' => trim((string) ($row->full_name ?? '')),
            'code' => filled($row->code ?? null) ? (string) $row->code : null,
            'job_title' => filled($row->job_title_name ?? null) ? (string) $row->job_title_name : null,
        ];
    }

    private function isActiveHrmEmployee(object $row): bool
    {
        $status = (string) ($row->status ?? 'active');

        return ! in_array($status, ['terminated', 'inactive'], true);
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
        if ($this->orgUnitsCache !== null) {
            return $this->orgUnitsCache;
        }

        $this->orgUnitsCache = DB::connection('hrm')
            ->table('org_units as ou')
            ->leftJoin('companies as c', 'c.id', '=', 'ou.company_id')
            ->whereNull('ou.deleted_at')
            ->get([
                'ou.id',
                'ou.uuid',
                'ou.parent_id',
                'ou.company_id',
                'ou.legacy_department_id',
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

        return $this->orgUnitsCache;
    }

    /** @return Collection<int|string, object> */
    private function orgUnitsById(): Collection
    {
        return $this->orgUnits()->keyBy('id');
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

    private function primaryAssignmentQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::connection('hrm')
            ->table('employees as e')
            ->leftJoin('employee_assignments as a', 'a.open_primary_employee_id', '=', 'e.id')
            ->whereNull('e.deleted_at');
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
                    'ou.company_id',
                    'ou.legacy_department_id',
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
        $catalog = $this->linkedCatalogDepartment($unit);
        $softwareCode = $this->softwareCodeForOrgUnit($unit, $catalog);

        return [
            'uuid' => (string) $unit->uuid,
            'code' => $unit->code,
            'software_code' => $softwareCode,
            'catalog_department_code' => $catalog !== null && filled($catalog->code ?? null)
                ? (string) $catalog->code
                : null,
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

    /**
     * Danh mục Tổ chức → Phòng ban (HRM `/organization/departments`).
     *
     * @return array{
     *   by_id: Collection<int|string, object>,
     *   by_company_code: array<string, object>,
     *   by_company_name: array<string, object>
     * }
     */
    private function hrmCatalogDepartments(): array
    {
        if ($this->hrmCatalogDepartments !== null) {
            return $this->hrmCatalogDepartments;
        }

        $rows = DB::connection('hrm')
            ->table('departments')
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->get(['id', 'uuid', 'company_id', 'code', 'name', 'software_code']);

        $byCompanyCode = [];
        $byCompanyName = [];
        foreach ($rows as $department) {
            $companyId = (int) ($department->company_id ?? 0);
            $codeKey = strtoupper(trim((string) ($department->code ?? '')));
            if ($codeKey !== '') {
                $byCompanyCode[$companyId.'|'.$codeKey] = $department;
            }
            $nameKey = mb_strtolower(trim((string) ($department->name ?? '')));
            if ($nameKey !== '') {
                $byCompanyName[$companyId.'|'.$nameKey] = $department;
            }
        }

        $this->hrmCatalogDepartments = [
            'by_id' => $rows->keyBy('id'),
            'by_company_code' => $byCompanyCode,
            'by_company_name' => $byCompanyName,
        ];

        return $this->hrmCatalogDepartments;
    }

    /** @return array<string, object> */
    private function hrmDivisionsByDeptKey(): array
    {
        if ($this->hrmDivisionsByDeptKey !== null) {
            return $this->hrmDivisionsByDeptKey;
        }

        $rows = DB::connection('hrm')
            ->table('divisions')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->get(['id', 'department_id', 'code', 'name']);

        $index = [];
        foreach ($rows as $division) {
            $deptId = (int) ($division->department_id ?? 0);
            $nameKey = $this->orgUnitNameKey((string) ($division->name ?? ''));
            if ($nameKey !== '') {
                $index[$deptId.'|n:'.$nameKey] = $division;
            }
            $codeKey = strtoupper(trim((string) ($division->code ?? '')));
            if ($codeKey !== '') {
                $index[$deptId.'|c:'.$codeKey] = $division;
            }
        }

        $this->hrmDivisionsByDeptKey = $index;

        return $this->hrmDivisionsByDeptKey;
    }

    /**
     * Khớp phòng ban danh mục HRM — cùng quy tắc sơ đồ tổ chức (legacy, leo cha, mã/tên).
     */
    private function linkedCatalogDepartment(object $unit): ?object
    {
        $companyId = (int) ($unit->company_id ?? 0);
        if ($companyId === 0) {
            return null;
        }

        $catalog = $this->hrmCatalogDepartments();
        $unitsById = $this->orgUnitsById();
        $cursor = $unit;

        for ($depth = 0; $depth < 6 && $cursor !== null; $depth++) {
            $legacyId = $cursor->legacy_department_id ?? null;
            if ($legacyId !== null) {
                $department = $catalog['by_id']->get($legacyId) ?? $catalog['by_id']->get((int) $legacyId);
                if ($department !== null) {
                    return $department;
                }
            }

            if ((string) ($cursor->type ?? '') === 'department') {
                $codeKey = strtoupper(trim((string) ($cursor->code ?? '')));
                if ($codeKey !== '') {
                    $department = $catalog['by_company_code'][$companyId.'|'.$codeKey] ?? null;
                    if ($department !== null) {
                        return $department;
                    }
                }

                $nameKey = mb_strtolower(trim((string) ($cursor->name ?? '')));
                if ($nameKey !== '') {
                    $department = $catalog['by_company_name'][$companyId.'|'.$nameKey] ?? null;
                    if ($department !== null) {
                        return $department;
                    }
                }
            }

            $parentId = $cursor->parent_id ?? null;
            if ($parentId === null) {
                break;
            }

            $cursor = $unitsById->get($parentId) ?? $unitsById->get((int) $parentId);
        }

        return null;
    }

    private function softwareCodeForOrgUnit(object $unit, ?object $catalogDepartment): ?string
    {
        if ((string) ($unit->type ?? '') === 'unit' && $catalogDepartment !== null) {
            $divisionCode = $this->divisionSoftwareCodeForUnit($catalogDepartment, $unit);
            if ($divisionCode !== null) {
                return $divisionCode;
            }
        }

        if ($catalogDepartment !== null) {
            $software = trim((string) ($catalogDepartment->software_code ?? ''));
            if ($software !== '') {
                return $software;
            }
        }

        return null;
    }

    private function divisionSoftwareCodeForUnit(object $department, object $unit): ?string
    {
        $deptId = (int) ($department->id ?? 0);
        if ($deptId === 0) {
            return null;
        }

        $index = $this->hrmDivisionsByDeptKey();
        $nameKey = $this->orgUnitNameKey((string) ($unit->name ?? ''));
        $division = null;
        if ($nameKey !== '') {
            $division = $index[$deptId.'|n:'.$nameKey] ?? null;
        }
        if ($division === null) {
            $unitCode = strtoupper(trim((string) ($unit->code ?? '')));
            if ($unitCode !== '') {
                $division = $index[$deptId.'|c:'.$unitCode] ?? null;
            }
        }

        if ($division === null) {
            return null;
        }

        $code = trim((string) ($division->code ?? ''));

        return $code !== '' ? $code : null;
    }

    private function orgUnitNameKey(string $name): string
    {
        $key = mb_strtolower(trim($name));

        return (string) preg_replace('/\s+/u', ' ', $key);
    }
}
