<?php

namespace Modules\Identity\App\Repositories;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Identity\App\Models\Role;
use Modules\Identity\App\Repositories\Contracts\RoleRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent (Role) trực tiếp.
 */
class RoleRepository implements RoleRepositoryInterface
{
    public function all(): Collection
    {
        return Role::query()->orderBy('id')->get();
    }

    public function findByCode(string $code): ?Role
    {
        return Role::query()->where('code', $code)->first();
    }

    public function syncForUser(int $userId, array $roleIds): void
    {
        $user = User::query()->findOrFail($userId);
        $user->roles()->sync($roleIds);
    }
}
