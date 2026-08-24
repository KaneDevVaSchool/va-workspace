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
