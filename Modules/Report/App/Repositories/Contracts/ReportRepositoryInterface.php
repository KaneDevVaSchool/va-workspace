<?php

namespace Modules\Report\App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Report\App\Models\Report;

interface ReportRepositoryInterface
{
    public function find(int $id): ?Report;

    /**
     * Báo cáo của một phòng ban (dành cho người quản lý phòng ban đó).
     *
     * @param  array{report_type?: string, q?: string}  $filters
     * @return Collection<int, Report>
     */
    public function allByDepartment(int $departmentId, array $filters = []): Collection;

    /**
     * Báo cáo mà một người được chia sẻ quyền xem.
     *
     * @param  array{report_type?: string, q?: string}  $filters
     * @return Collection<int, Report>
     */
    public function allSharedWithUser(int $userId, array $filters = []): Collection;

    /** Toàn bộ báo cáo mọi phòng ban — chỉ dùng cho vai trò xem toàn hệ thống. */
    public function allAcrossDepartments(array $filters = []): Collection;

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Report;

    /** @param  array<string, mixed>  $data */
    public function update(Report $report, array $data): Report;

    public function delete(Report $report): void;

    /**
     * Ghi đè toàn bộ danh sách người xem của báo cáo.
     *
     * @param  list<int>  $userIds
     */
    public function syncViewers(Report $report, array $userIds): void;

    /**
     * Ghi đè bộ lọc phạm vi (hiện chỉ dùng khoá `user_id`).
     *
     * @param  list<int>  $userIds
     */
    public function syncUserFilters(Report $report, array $userIds): void;

    /**
     * Ghi đè danh sách cột hiển thị, thứ tự theo đúng thứ tự truyền vào.
     *
     * @param  list<string>  $columnKeys
     */
    public function syncColumns(Report $report, array $columnKeys): void;

    /**
     * Ghi đè danh sách tiêu chí được đưa vào báo cáo.
     *
     * @param  list<int>  $criterionIds
     */
    public function syncCriteria(Report $report, array $criterionIds): void;

    /**
     * Báo cáo đánh giá nhân sự đã lưu có kỳ giao với khoảng ngày.
     *
     * Dùng để khoá ghi nhận / xoá điểm trên bảng tổng hợp khi tháng đó đã
     * chốt báo cáo — không tải viewers hay snapshot, chỉ cần kỳ và tiêu đề.
     *
     * @return Collection<int, Report>
     */
    public function savedPersonnelOverlapping(int $departmentId, string $from, string $to): Collection;

    /**
     * Cùng phạm vi với allByDepartment / allSharedWithUser / allAcrossDepartments
     * nhưng phân trang ở máy chủ — danh sách vài nghìn báo cáo không tải hết
     * về trình duyệt được.
     *
     * @param  array{report_type?: string, q?: string}  $filters
     * @param  'department'|'shared'|'all'  $scope
     */
    public function paginateVisible(
        string $scope,
        int $scopeId,
        array $filters,
        int $perPage,
        int $page,
    ): LengthAwarePaginator;
}
