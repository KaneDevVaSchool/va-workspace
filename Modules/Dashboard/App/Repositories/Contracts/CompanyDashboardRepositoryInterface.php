<?php

namespace Modules\Dashboard\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Project\App\Models\Project;

/**
 * Tầng duy nhất được phép gọi Eloquent trực tiếp cho dữ liệu Dashboard tổng
 * công ty — chỉ đọc (không CRUD), tổng hợp từ Project/Task/Department có sẵn.
 */
interface CompanyDashboardRepositoryInterface
{
    /** Tổng số dự án, tổng số công việc (task lá) trong phạm vi lọc. */
    public function counts(array $filters): array;

    /** Số dự án theo từng trạng thái (ProjectEnums::STATUSES). */
    public function projectsByStatus(array $filters): array;

    /** Danh sách project id + field cần thiết để tính tiến độ/health/aging. */
    public function projectsForCalculation(array $filters): Collection;

    /** Tổng hợp theo phòng ban: đang chạy / trễ / hoàn thành, kèm project_ids để tính tiến độ. */
    public function departmentsRaw(array $filters): Collection;

    /** Đếm dự án có start_date/end_date rơi vào từng tháng quanh hiện tại. */
    public function timeline(array $filters, int $monthsBefore, int $monthsAfter): array;

    /** Đếm task rơi vào từng tháng quanh hiện tại (theo project thuộc phạm vi lọc). */
    public function taskTimeline(array $filters, int $monthsBefore, int $monthsAfter): array;

    /**
     * Toàn bộ dự án khớp filter cơ bản (status/department/q/sort) — KHÔNG
     * phân trang ở DB vì health/overdue_bucket cần tính ở tầng PHP trước khi
     * cắt trang (xem CompanyDashboardService::projects()).
     */
    public function listProjectsForTable(array $filters): Collection;

    public function findProject(int $projectId): ?Project;

    /** Số công việc hoàn thành / tổng công việc lá của 1 dự án. */
    public function taskCountsForProject(int $projectId): array;

    /**
     * Số công việc hoàn thành / tổng công việc lá cho NHIỀU dự án cùng lúc
     * (1 query, tránh N+1 khi build bảng dự án).
     *
     * @param  list<int>  $projectIds
     * @return array<int, array{tasks_total: int, tasks_completed: int}>
     */
    public function taskCountsForProjects(array $projectIds): array;
}
