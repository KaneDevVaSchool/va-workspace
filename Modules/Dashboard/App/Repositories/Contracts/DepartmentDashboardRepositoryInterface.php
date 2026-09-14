<?php

namespace Modules\Dashboard\App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Tầng duy nhất được phép gọi Eloquent trực tiếp cho dữ liệu Dashboard
 * phòng ban — chỉ đọc, tổng hợp từ Project/Task/User/Team có sẵn.
 */
interface DepartmentDashboardRepositoryInterface
{
    public function departmentName(int $departmentId): ?string;

    /** id các dự án thuộc phạm vi phòng ban/nhóm (owner/executing/pivot). */
    public function projectIdsForDepartment(int $departmentId, ?int $teamId = null): array;

    /** id các user thuộc phòng ban (tuỳ chọn lọc theo team). */
    public function userIdsForDepartment(int $departmentId, ?int $teamId = null): Collection;

    public function counts(array $projectIds, Collection $userIds): array;

    public function tasksByStatus(array $projectIds, Collection $userIds): array;

    /** Danh sách task lá thuộc phạm vi để tính work aging + tiến độ nhân viên. */
    public function tasksForCalculation(array $projectIds, Collection $userIds): Collection;

    /** Danh sách nhân viên phân trang kèm project/task đã tham gia. */
    public function paginateEmployees(int $departmentId, ?int $teamId, array $filters, int $perPage, int $page): LengthAwarePaginator;

    public function findUser(int $userId): ?User;

    /** Danh sách task của 1 nhân viên trong phạm vi (kèm project name). */
    public function tasksForUser(int $userId, array $allowedProjectIds): Collection;

    /**
     * Dự án (trong phạm vi phòng ban) mà từng user là người phụ trách
     * (Project.lead_user_id) hoặc đang phối hợp (Project.members, trừ
     * chính người phụ trách).
     *
     * @param  list<int>  $userIds
     * @return array{leading: array<int, Collection>, collaborating: array<int, Collection>} khoá là user_id, mỗi phần tử là Collection<Project>
     */
    public function projectRolesForUsers(array $projectIds, array $userIds): array;
}
