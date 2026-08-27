<?php

namespace Modules\Evaluation\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Evaluation\App\Models\EvaluationTemplate;

interface EvaluationTemplateRepositoryInterface
{
    /** Mẫu của 1 phòng ban + mọi mẫu is_global (mọi phòng ban đều thấy được). */
    public function visibleForDepartment(int $departmentId): Collection;

    /** Toàn bộ mẫu mọi phòng ban — chỉ dùng cho super_admin (workspace_config.view_all). */
    public function all(): Collection;

    public function find(int $id): ?EvaluationTemplate;

    /** Tìm theo id, chỉ trả về nếu thuộc phòng ban hoặc is_global = true. */
    public function findVisibleForDepartment(int $id, int $departmentId): ?EvaluationTemplate;

    public function create(array $data): EvaluationTemplate;

    public function update(EvaluationTemplate $template, array $data): EvaluationTemplate;

    public function delete(EvaluationTemplate $template): bool;

    public function toggleActive(EvaluationTemplate $template, ?int $updatedBy = null): EvaluationTemplate;

    public function toggleGlobal(EvaluationTemplate $template, ?int $updatedBy = null): EvaluationTemplate;

    /**
     * Ghi đè toàn bộ danh sách tiêu chí của mẫu (xoá cũ, tạo lại theo thứ tự truyền vào).
     *
     * @param  list<array{evaluation_criteria_id: int, weight_percent: int, required_score: int|null, count_in_total: bool}>  $rows
     */
    public function syncCriteria(EvaluationTemplate $template, array $rows): EvaluationTemplate;

    /** Ghi đè toàn bộ vị trí đánh giá áp dụng cho mẫu (N-N, không giữ dữ liệu phụ trên pivot). */
    public function syncPositions(EvaluationTemplate $template, array $positionIds): EvaluationTemplate;

    /**
     * Ghi đè toàn bộ trường tùy biến của mẫu (xoá cũ, tạo lại theo thứ tự truyền vào).
     *
     * @param  list<array{label: string, field_type: string, options: list<string>|null, is_required: bool}>  $rows
     */
    public function syncCustomFields(EvaluationTemplate $template, array $rows): EvaluationTemplate;

    /** Mã tiếp theo dạng số thuần (chưa format EVT-xxxx), tăng dần toàn hệ thống. */
    public function nextCodeSequence(): int;
}
