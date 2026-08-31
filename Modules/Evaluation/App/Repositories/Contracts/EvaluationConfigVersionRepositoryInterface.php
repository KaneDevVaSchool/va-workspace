<?php

namespace Modules\Evaluation\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Evaluation\App\Models\EvaluationConfigVersion;

interface EvaluationConfigVersionRepositoryInterface
{
    /** Phiên bản đang áp dụng của phòng ban (nếu đã từng chốt). */
    public function activeForDepartment(int $departmentId): ?EvaluationConfigVersion;

    public function find(int $id): ?EvaluationConfigVersion;

    /** @return Collection<int, EvaluationConfigVersion> */
    public function allByDepartment(int $departmentId): Collection;

    /** Số hiệu phiên bản lớn nhất đã dùng của phòng ban (0 nếu chưa có). */
    public function maxVersionNo(int $departmentId): int;

    /** Đẩy toàn bộ phiên bản đang áp dụng của phòng ban sang trạng thái cũ. */
    public function supersedeActive(int $departmentId): void;

    /** @param  array<string, mixed>  $data */
    public function create(array $data): EvaluationConfigVersion;
}
