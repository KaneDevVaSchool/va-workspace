<?php

namespace Modules\Identity\App\Services;

use App\Models\User;
use Modules\Identity\App\Hrm\Services\HrmDepartmentSyncService;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDatabaseMirrorService;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;

/**
 * Tìm đồng nghiệp. Có HRM_DB_* thì danh sách lấy từ MySQL HRM, users.id chỉ là
 * bản đồ tài khoản (tạo/cập nhật lúc chọn).
 */
class WorkspaceColleagueSearchService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly HrmEmployeeDatabaseMirrorService $mirror,
        private readonly HrmDepartmentSyncService $hrmDepartments,
    ) {}

    /**
     * @return list<array{id: int, name: string, avatar_url: ?string, department: ?string, job_title_name: ?string, company_name: ?string}>
     */
    public function search(string $query, int $limit = 8, ?int $excludeUserId = null): array
    {
        if (HrmEmployeeDirectory::isConfigured()) {
            return $this->mirror->mappedSearch($query, $limit, $excludeUserId);
        }

        return $this->users->searchActiveByName($query, $limit, $excludeUserId)
            ->map(fn (User $user) => $this->present($user))
            ->values()
            ->all();
    }

    /**
     * Một trang kết quả (cuộn tải thêm) — Chat phải tìm được toàn bộ đồng nghiệp, không dừng ở vài người đầu.
     *
     * @return array{users: list<array{id: int, name: string, avatar_url: ?string, department: ?string, job_title_name: ?string, company_name: ?string}>, has_more: bool}
     */
    public function searchPage(string $query, int $limit = 20, int $offset = 0, ?int $excludeUserId = null): array
    {
        if (HrmEmployeeDirectory::isConfigured()) {
            return $this->mirror->mappedSearchPage($query, $limit, $offset, $excludeUserId);
        }

        $users = $this->users->searchActiveByName($query, $limit + 1, $excludeUserId, $offset);

        return [
            'users' => $users->take($limit)->map(fn (User $user) => $this->present($user))->values()->all(),
            'has_more' => $users->count() > $limit,
        ];
    }

    /** @return array{id: int, name: string, avatar_url: ?string, department: ?string, job_title_name: ?string, company_name: ?string} */
    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatar_url,
            'department' => $user->department?->name,
            'job_title_name' => $this->hrmDepartments->hrmJobTitleFor($user),
            'company_name' => $user->resolveCompanyName(),
        ];
    }
}
