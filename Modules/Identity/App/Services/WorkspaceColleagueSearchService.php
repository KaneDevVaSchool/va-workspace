<?php

namespace Modules\Identity\App\Services;

use App\Models\User;
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
    ) {}

    /**
     * @return list<array{id: int, name: string, avatar_url: ?string, department: ?string, company_name: ?string}>
     */
    public function search(string $query, int $limit = 8, ?int $excludeUserId = null): array
    {
        if (HrmEmployeeDirectory::isConfigured()) {
            return $this->mirror->mappedSearch($query, $limit, $excludeUserId);
        }

        return $this->users->searchActiveByName($query, $limit, $excludeUserId)
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar_url,
                'department' => $user->department?->name,
                'company_name' => $user->resolveCompanyName(),
            ])
            ->values()
            ->all();
    }
}
