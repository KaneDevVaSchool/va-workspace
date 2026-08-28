<?php

namespace Modules\Project\App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Project\App\Models\Task;

/**
 * Contract cho tầng Repository của Task — Service chỉ phụ thuộc interface
 * này, không phụ thuộc trực tiếp Eloquent.
 */
interface TaskRepositoryInterface
{
    /**
     * Xuyên project — trang "Tất cả công việc".
     *
     * @param  array<string, mixed>  $filters
     * @param  list<int>  $allowedProjectIds  danh sách project_id viewer được xem (RBAC = RBAC Project chứa Task)
     */
    public function paginate(array $filters, int $perPage, int $page, array $allowedProjectIds): LengthAwarePaginator;

    /** Toàn bộ Task của 1 project, dạng phẳng, sắp theo parent_id/sort_order — dùng để build cây WBS. */
    public function flatForProject(int $projectId): Collection;

    public function find(int $id): ?Task;

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Task;

    /** @param  array<string, mixed>  $data */
    public function update(Task $task, array $data): Task;

    public function delete(Task $task): bool;

    public function hasChildren(int $taskId): bool;

    /** Toàn bộ id con cháu (đệ quy) — dùng chặn vòng lặp khi đổi parent_id. */
    public function descendantIds(int $taskId): array;

    /** Đếm tổng số Task của 1 project — dùng cho baseline (work_items). */
    public function countByProject(int $projectId): int;
}
