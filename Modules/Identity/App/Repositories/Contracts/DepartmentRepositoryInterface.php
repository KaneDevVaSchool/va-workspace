<?php

namespace Modules\Identity\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Identity\App\Models\Department;

/**
 * TẠM THỜI: dữ liệu phòng ban giả lập (bảng phẳng, không cây cấp bậc).
 * Sẽ đổi implementation sang gọi API HRM khi có — Controller/Service
 * không cần sửa vì chỉ phụ thuộc interface này.
 */
interface DepartmentRepositoryInterface
{
    /** @return Collection<int, Department> */
    public function allActive(): Collection;

    public function find(int $id): ?Department;
}
