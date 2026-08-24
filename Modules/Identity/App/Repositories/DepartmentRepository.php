<?php

namespace Modules\Identity\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Repositories\Contracts\DepartmentRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent (Department) trực tiếp.
 * TẠM THỜI — sẽ bị thay bằng implementation gọi API HRM.
 */
class DepartmentRepository implements DepartmentRepositoryInterface
{
    public function allActive(): Collection
    {
        return Department::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function find(int $id): ?Department
    {
        return Department::query()->find($id);
    }
}
