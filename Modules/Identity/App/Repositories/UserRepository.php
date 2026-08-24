<?php

namespace Modules\Identity\App\Repositories;

use App\Models\User;
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

    public function findById(int $id): ?User
    {
        return User::query()->find($id);
    }

    public function allActiveByDepartment(int $departmentId): \Illuminate\Support\Collection
    {
        return User::query()
            ->where('department_id', $departmentId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public function allByDepartment(int $departmentId): \Illuminate\Support\Collection
    {
        return User::query()
            ->where('department_id', $departmentId)
            ->with(['team', 'roles'])
            ->orderBy('name')
            ->get();
    }

    public function countByDepartmentIds(array $departmentIds): \Illuminate\Support\Collection
    {
        if ($departmentIds === []) {
            return collect();
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

        return User::query()
            ->whereIn('department_id', $departmentIds)
            ->whereHas('roles', fn ($query) => $query->where('code', 'department_director'))
            ->orderBy('name')
            ->get()
            ->unique('department_id')
            ->keyBy('department_id');
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
