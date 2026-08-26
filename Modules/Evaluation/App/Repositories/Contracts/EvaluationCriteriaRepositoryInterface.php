<?php

namespace Modules\Evaluation\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Evaluation\App\Models\EvaluationCriteria;

interface EvaluationCriteriaRepositoryInterface
{
    /** Tất cả tiêu chí của một phòng ban, sắp xếp theo sort_order, name. */
    public function allByDepartment(int $departmentId): Collection;

    /**
     * Tất cả tiêu chí ĐANG HOẠT ĐỘNG của MỌI phòng ban — chỉ dùng khi build
     * mẫu đánh giá dùng chung toàn hệ thống (is_global = true), nơi
     * department_director+ được chọn tiêu chí cross-department. Xem
     * plans/2026-08-26-mau-danh-gia.md §4, PR4.
     */
    public function allActiveAcrossDepartments(): Collection;

    /** @return list<int> */
    public function idsByDepartment(int $departmentId): array;

    /** Tên tiêu chí (đã lowercase, trim) hiện có trong phòng ban — dùng phát hiện trùng khi nhập Excel. */
    public function namesByDepartment(int $departmentId): array;

    /**
     * Đã tồn tại tiêu chí cùng tên (không phân biệt hoa/thường, đã trim) trong phòng ban chưa —
     * dùng chặn đặt trùng tên khi tạo/sửa tiêu chí qua form. $exceptId loại trừ chính bản ghi
     * đang sửa (update).
     */
    public function existsNameInDepartment(int $departmentId, string $name, ?int $exceptId = null): bool;

    public function find(int $id): ?EvaluationCriteria;

    /** Tìm theo id và đảm bảo thuộc phòng ban được phép. */
    public function findByDepartment(int $id, int $departmentId): ?EvaluationCriteria;

    public function create(array $data): EvaluationCriteria;

    public function update(EvaluationCriteria $criterion, array $data): EvaluationCriteria;

    public function delete(EvaluationCriteria $criterion): bool;

    /** Đổi trạng thái is_active. */
    public function toggleActive(EvaluationCriteria $criterion, ?int $updatedBy = null): EvaluationCriteria;

    /** Đổi trạng thái use_in_evaluation (hiện trên ĐGNL). */
    public function toggleUseInEvaluation(EvaluationCriteria $criterion, ?int $updatedBy = null): EvaluationCriteria;

    /** Cập nhật sort_order theo mảng IDs (giữ nguyên phòng ban). */
    public function reorder(int $departmentId, array $orderedIds): void;
}
