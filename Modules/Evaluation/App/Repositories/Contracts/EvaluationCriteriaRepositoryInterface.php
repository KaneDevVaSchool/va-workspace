<?php

namespace Modules\Evaluation\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Evaluation\App\Models\EvaluationCriteria;

interface EvaluationCriteriaRepositoryInterface
{
    /** Tất cả tiêu chí của một phòng ban, sắp xếp theo sort_order, name. */
    public function allByDepartment(int $departmentId): Collection;

    public function find(int $id): ?EvaluationCriteria;

    /** Tìm theo id và đảm bảo thuộc phòng ban được phép. */
    public function findByDepartment(int $id, int $departmentId): ?EvaluationCriteria;

    public function create(array $data): EvaluationCriteria;

    public function update(EvaluationCriteria $criterion, array $data): EvaluationCriteria;

    public function delete(EvaluationCriteria $criterion): bool;

    /** Đổi trạng thái is_active. */
    public function toggleActive(EvaluationCriteria $criterion): EvaluationCriteria;

    /** Cập nhật sort_order theo mảng IDs (giữ nguyên phòng ban). */
    public function reorder(int $departmentId, array $orderedIds): void;
}
