<?php

namespace Modules\Identity\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Identity\App\Models\Role;

interface RoleRepositoryInterface
{
    /** @return Collection<int, Role> */
    public function all(): Collection;

    public function findByCode(string $code): ?Role;

    /** @param  array<int, int>  $roleIds */
    public function syncForUser(int $userId, array $roleIds): void;
}
