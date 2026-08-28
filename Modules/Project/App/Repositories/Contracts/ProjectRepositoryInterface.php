<?php

namespace Modules\Project\App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Identity\App\Models\Department;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\ProjectAttachment;
use Modules\Project\App\Models\ProjectLabel;
use Modules\Project\App\Models\ProjectSetting;
use Modules\Project\App\Models\ProjectQuickItem;
use Modules\Project\App\Models\ProjectType;

/**
 * Contract cho tầng Repository — Service chỉ phụ thuộc interface này,
 * không phụ thuộc trực tiếp Eloquent.
 */
interface ProjectRepositoryInterface
{
    /** @param  array<string, mixed>  $filters */
    public function paginate(array $filters, int $perPage, int $page, User $viewer): LengthAwarePaginator;

    /** @param  array<string, mixed>  $filters */
    public function forExport(array $filters, User $viewer): Collection;

    public function findUserByEmail(string $email): ?User;

    public function findDepartmentByName(string $name): ?Department;

    public function find(int $id): ?Project;

    /** Tra dự án theo Mã dự án (không phân biệt hoa/thường) — dùng cho nhập Excel cập nhật. */
    public function findByCode(string $code): ?Project;

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Project;

    /** @param  array<string, mixed>  $data */
    public function update(Project $project, array $data): Project;

    public function delete(Project $project): bool;

    /** @param  array<int, array<string, mixed>>  $rows */
    public function replaceScopes(Project $project, array $rows): Project;

    /** @param  list<int>  $userIds */
    public function replaceMembers(Project $project, array $userIds): Project;

    /** @param  list<int>  $userIds */
    public function replaceFollowers(Project $project, array $userIds): Project;

    /** @param  list<int>  $labelIds */
    public function replaceLabels(Project $project, array $labelIds): Project;

    /** @param  list<int>  $departmentIds */
    public function replaceExecutingDepartments(Project $project, array $departmentIds): Project;

    public function addAttachment(int $projectId, array $data): ProjectAttachment;

    public function findAttachment(int $projectId, int $attachmentId): ?ProjectAttachment;

    public function deleteAttachment(ProjectAttachment $attachment): bool;

    /** Mã dự án tiếp theo sinh theo mẫu trong project_settings — xem ProjectSetting. */
    public function nextCode(): string;

    /** Xem trước mã tiếp theo mà KHÔNG lưu/tăng bộ đếm thật. */
    public function previewNextCode(): string;

    /** Danh sách user cùng phòng ban (dùng cho "Người thực hiện"/"Phụ trách chính"). */
    public function assignableUsersInDepartment(?int $departmentId): Collection;

    public function allUsers(): Collection;

    /** Áp bộ lọc quyền xem dự án theo viewer (mục A) lên query đã build sẵn. */
    public function forViewer(Builder $query, User $viewer): Builder;

    /** @return array<string, int> */
    public function tabCounts(User $viewer): array;

    // ---------- Follower (mục B) ----------
    public function addFollower(int $projectId, int $userId): void;

    public function removeFollower(int $projectId, int $userId): void;

    public function isFollowing(int $projectId, int $userId): bool;

    // ---------- Allowlist tạo dự án (mục C) ----------
    public function isInCreatorAllowlist(int $userId): bool;

    /** @return list<array{id:int,name:string,email:string|null,avatar_url:string|null}> */
    public function creatorAllowlistUsers(): array;

    /** @param  list<int>  $userIds */
    public function replaceCreatorAllowlist(array $userIds): void;

    // ---------- Cài đặt dự án (mục D) ----------
    public function getSettings(): ProjectSetting;

    /** @param  array<string, mixed>  $data */
    public function updateSettings(array $data): ProjectSetting;

    /** Chuyển các dự án đủ điều kiện (planning + start_date <= hôm nay) sang in_progress. Trả về số lượng đã update. */
    public function autoStartEligibleProjects(): int;

    // ---------- Nhãn (mục E) ----------
    /** @return list<array{id:int,name:string,color:string}> */
    public function allLabels(): array;

    public function findLabelByName(string $name): ?ProjectLabel;

    /** @param  array<string, mixed>  $data */
    public function createLabel(array $data): ProjectLabel;

    // ---------- Loại dự án (mục A) ----------
    /** @return list<array{id:int,name:string}> */
    public function allTypes(): array;

    public function findTypeByName(string $name): ?ProjectType;

    /** @param  array<string, mixed>  $data */
    public function createType(array $data): ProjectType;

    // ---------- Mục nhanh từ menu chuột phải ----------
    /** @return Collection<int, ProjectQuickItem> */
    public function listQuickItems(int $projectId, ?string $kind = null): Collection;

    /** @param  array<string, mixed>  $data */
    public function createQuickItem(int $projectId, array $data): ProjectQuickItem;

    public function countQuickItemsByKind(int $projectId): array;
}
