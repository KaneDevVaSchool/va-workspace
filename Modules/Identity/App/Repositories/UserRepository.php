<?php

namespace Modules\Identity\App\Repositories;

use App\Models\User;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDatabaseMirrorService;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent (App\Models\User) trực tiếp.
 *
 * TẠM THỜI — sẽ bị thay bằng implementation gọi API HRM khi HRM cung cấp
 * (xem UserRepositoryInterface). Không thêm business logic ở đây ngoài
 * truy vấn/ghi dữ liệu thuần.
 */
class UserRepository implements UserRepositoryInterface
{
    public function findByGoogleId(string $googleId): ?User
    {
        return User::query()->where('google_id', $googleId)->first();
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
    }

    public function findByHrmUserUuid(string $hrmUserUuid): ?User
    {
        return User::query()->where('hrm_user_uuid', $hrmUserUuid)->first();
    }

    public function findByHrmEmployeeUuid(string $hrmEmployeeUuid): ?User
    {
        return User::query()->where('hrm_employee_uuid', $hrmEmployeeUuid)->first();
    }

    public function findById(int $id): ?User
    {
        return User::query()->find($id);
    }

    public function allActiveByDepartment(int $departmentId): \Illuminate\Support\Collection
    {
        if (HrmEmployeeDirectory::isConfigured()) {
            return app(HrmEmployeeDatabaseMirrorService::class)->mappedByDepartment($departmentId, true);
        }

        return User::query()
            ->where('department_id', $departmentId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public function allByDepartment(int $departmentId): \Illuminate\Support\Collection
    {
        if (HrmEmployeeDirectory::isConfigured()) {
            return app(HrmEmployeeDatabaseMirrorService::class)->mappedByDepartment($departmentId);
        }

        return User::query()
            ->where('department_id', $departmentId)
            ->with(['team', 'roles', 'company', 'concurrentPositions'])
            ->orderBy('name')
            ->get();
    }

    public function allWithAssignedDepartment(): \Illuminate\Support\Collection
    {
        if (HrmEmployeeDirectory::isConfigured()) {
            $departmentIds = \Modules\Identity\App\Models\Department::query()
                ->whereNotNull('hrm_org_unit_uuid')
                ->pluck('id')
                ->all();

            return app(HrmEmployeeDatabaseMirrorService::class)->mappedActive($departmentIds);
        }

        return User::query()
            ->whereNotNull('department_id')
            ->with(['department', 'team', 'roles', 'company', 'concurrentPositions'])
            ->orderBy('name')
            ->get();
    }

    public function countByDepartmentIds(array $departmentIds): \Illuminate\Support\Collection
    {
        if ($departmentIds === []) {
            return collect();
        }

        if (HrmEmployeeDirectory::isConfigured()) {
            return app(HrmEmployeeDatabaseMirrorService::class)->countsByWorkspaceDepartment($departmentIds);
        }

        return User::query()
            ->whereIn('department_id', $departmentIds)
            ->selectRaw('department_id, COUNT(*) as aggregate')
            ->groupBy('department_id')
            ->pluck('aggregate', 'department_id')
            ->mapWithKeys(fn ($count, $id) => [(int) $id => (int) $count]);
    }

    public function departmentDirectorsByDepartmentIds(array $departmentIds): \Illuminate\Support\Collection
    {
        if ($departmentIds === []) {
            return collect();
        }

        if (HrmEmployeeDirectory::isConfigured()) {
            $ids = collect($departmentIds)
                ->flatMap(fn ($departmentId) => app(HrmEmployeeDatabaseMirrorService::class)->mappedByDepartment((int) $departmentId)->pluck('id'))
                ->unique()
                ->values();

            return User::query()
                ->whereIn('id', $ids)
                ->whereHas('roles', fn ($query) => $query->where('code', 'department_director'))
                ->orderBy('name')
                ->get()
                ->unique('department_id')
                ->keyBy('department_id');
        }

        return User::query()
            ->whereIn('department_id', $departmentIds)
            ->whereHas('roles', fn ($query) => $query->where('code', 'department_director'))
            ->orderBy('name')
            ->get()
            ->unique('department_id')
            ->keyBy('department_id');
    }

    public function searchActiveByName(string $query, int $limit = 8, ?int $excludeUserId = null): \Illuminate\Support\Collection
    {
        if (HrmEmployeeDirectory::isConfigured()) {
            return collect(app(HrmEmployeeDatabaseMirrorService::class)->mappedSearch($query, $limit, $excludeUserId))
                ->map(fn (array $row) => User::query()->find($row['id']))
                ->filter();
        }

        $builder = User::query()
            ->with(['department.company', 'company'])
            ->where('status', 'active')
            ->orderBy('name')
            ->limit($limit);

        $needle = trim($query);
        if ($needle !== '') {
            $escaped = addcslashes($needle, '%_\\');
            $builder->where('name', 'like', '%'.$escaped.'%');
        }

        if ($excludeUserId !== null) {
            $builder->where('id', '!=', $excludeUserId);
        }

        return $builder->get();
    }

    public function findActiveByIds(array $ids): \Illuminate\Support\Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return collect();
        }

        $query = User::query()
            ->whereIn('id', $ids)
            ->where('status', 'active');
        if (HrmEmployeeDirectory::isConfigured()) {
            $query->whereNotNull('hrm_employee_uuid')->where('hrm_employee_uuid', '!=', '');
        }

        return $query->get();
    }

    public function allActiveSuperAdmins(): \Illuminate\Support\Collection
    {
        return User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('code', 'super_admin'))
            ->orderBy('name')
            ->get();
    }

    public function allActiveWithRoles(): \Illuminate\Support\Collection
    {
        return User::query()
            ->where('status', 'active')
            ->with('roles')
            ->orderBy('name')
            ->get();
    }

    public function allUnassigned(): \Illuminate\Support\Collection
    {
        if (HrmEmployeeDirectory::isConfigured()) {
            return app(HrmEmployeeDatabaseMirrorService::class)->mappedWithoutDepartment();
        }

        return User::query()
            ->whereNull('department_id')
            ->with(['roles', 'company', 'concurrentPositions'])
            ->orderBy('name')
            ->get();
    }

    public function allActivePersonnel(?array $departmentIds = null): \Illuminate\Support\Collection
    {
        if (HrmEmployeeDirectory::isConfigured()) {
            return app(HrmEmployeeDatabaseMirrorService::class)->mappedActive($departmentIds);
        }

        $query = User::query()->where('status', 'active')->orderBy('name');
        if ($departmentIds !== null) {
            $query->whereIn('department_id', $departmentIds);
        }

        return $query->get();
    }

    public function create(array $data): User
    {
        return User::query()->create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->fill($data)->save();

        return $user;
    }
}
