<?php

namespace Modules\Project\App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Services\PermissionService;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\ProjectAttachment;
use Modules\Project\App\Models\ProjectCreatorAllowlist;
use Modules\Project\App\Models\ProjectFolder;
use Modules\Project\App\Models\ProjectFollower;
use Modules\Project\App\Models\ProjectLabel;
use Modules\Project\App\Models\ProjectQuickItem;
use Modules\Project\App\Models\ProjectScope;
use Modules\Project\App\Models\ProjectSetting;
use Modules\Project\App\Models\ProjectType;
use Modules\Project\App\Repositories\Contracts\ProjectRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent trực tiếp cho module Project.
 */
class ProjectRepository implements ProjectRepositoryInterface
{
    public function paginate(array $filters, int $perPage, int $page, User $viewer): LengthAwarePaginator
    {
        $query = Project::query()->with(Project::WITH_PRESENT);

        $this->applyCommonFilters($query, $filters);
        $this->applyTabFilter($query, $filters['tab'] ?? null, $viewer);
        $this->forViewer($query, $viewer);

        return $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);
    }

    public function forExport(array $filters, User $viewer): Collection
    {
        $query = Project::query()->with(Project::WITH_PRESENT);

        $this->applyCommonFilters($query, $filters);
        $this->applyTabFilter($query, $filters['tab'] ?? null, $viewer);
        $this->forViewer($query, $viewer);

        return $query->orderByDesc('created_at')->get();
    }

    public function findUserByEmail(string $email): ?User
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        return User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
    }

    public function findDepartmentByName(string $name): ?Department
    {
        $name = mb_strtolower(trim($name));
        if ($name === '') {
            return null;
        }

        return Department::query()
            ->where(function (Builder $query) use ($name) {
                $query->whereRaw('LOWER(name) = ?', [$name])
                    ->orWhereRaw('LOWER(code) = ?', [$name]);
            })
            ->first();
    }

    /** @param  array<string, mixed>  $filters */
    private function applyCommonFilters(Builder $query, array $filters): void
    {
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['importance'])) {
            $query->where('importance', $filters['importance']);
        }

        if (! empty($filters['lead_user_id'])) {
            $query->where('lead_user_id', $filters['lead_user_id']);
        }

        $rawLabelIds = $filters['label_ids'] ?? [];
        if (! is_array($rawLabelIds)) {
            $rawLabelIds = $rawLabelIds === null || $rawLabelIds === '' ? [] : [$rawLabelIds];
        }
        $labelIds = array_values(array_filter(array_map('intval', $rawLabelIds)));
        if ($labelIds !== []) {
            $query->whereHas('labels', function ($sub) use ($labelIds) {
                $sub->whereIn('project_labels.id', $labelIds);
            });
        }
    }

    private function applyTabFilter(Builder $query, ?string $tab, User $viewer): void
    {
        if ($tab === null || $tab === '' || $tab === 'all') {
            return;
        }

        $statusTabs = ['in_progress', 'completed', 'on_hold', 'planning', 'cancelled'];
        if (in_array($tab, $statusTabs, true)) {
            $query->where('status', $tab);

            return;
        }

        match ($tab) {
            'following' => $query->whereHas('followers', fn ($sub) => $sub->where('users.id', $viewer->id)),
            'my_tasks' => $query->where(function ($sub) use ($viewer) {
                $sub->where('lead_user_id', $viewer->id)
                    ->orWhereHas('members', fn ($m) => $m->where('users.id', $viewer->id));
            }),
            'my_department' => $query->where(function ($sub) use ($viewer) {
                $sub->where('owner_department_id', $viewer->department_id)
                    ->orWhere('executing_department_id', $viewer->department_id)
                    ->orWhereHas('executingDepartments', fn ($d) => $d->where('departments.id', $viewer->department_id));
            }),
            default => null,
        };
    }

    public function find(int $id): ?Project
    {
        return Project::query()->with(Project::WITH_PRESENT)->find($id);
    }

    public function findForViewer(int $id, User $viewer): ?Project
    {
        $query = Project::query()->with(Project::WITH_PRESENT)->whereKey($id);
        $this->forViewer($query, $viewer);

        return $query->first();
    }

    public function findByCode(string $code): ?Project
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        return Project::query()
            ->with(Project::WITH_PRESENT)
            ->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])
            ->first();
    }

    public function create(array $data): Project
    {
        $project = Project::query()->create($data);

        return $project->fresh(Project::WITH_PRESENT);
    }

    public function update(Project $project, array $data): Project
    {
        $project->update($data);

        return $project->fresh(Project::WITH_PRESENT);
    }

    public function delete(Project $project): bool
    {
        return (bool) $project->delete();
    }

    public function replaceScopes(Project $project, array $rows): Project
    {
        DB::transaction(function () use ($project, $rows) {
            ProjectScope::query()->where('project_id', $project->id)->delete();

            foreach ($rows as $row) {
                ProjectScope::query()->create([
                    'project_id' => $project->id,
                    'scope_type' => $row['scope_type'],
                    'department_id' => $row['department_id'] ?? null,
                    'weight_percent' => $row['weight_percent'] ?? 0,
                ]);
            }
        });

        return $project->fresh(Project::WITH_PRESENT);
    }

    public function replaceMembers(Project $project, array $userIds): Project
    {
        $project->members()->sync($userIds);

        return $project->fresh(Project::WITH_PRESENT);
    }

    public function replaceLabels(Project $project, array $labelIds): Project
    {
        $project->labels()->sync($labelIds);

        return $project->fresh(Project::WITH_PRESENT);
    }

    public function replaceFollowers(Project $project, array $userIds): Project
    {
        $project->followers()->sync($userIds);

        return $project->fresh(Project::WITH_PRESENT);
    }

    public function replaceExecutingDepartments(Project $project, array $departmentIds): Project
    {
        $ids = array_values(array_unique(array_map('intval', $departmentIds)));
        $project->executingDepartments()->sync($ids);

        $project->update([
            'executing_department_id' => $ids[0] ?? null,
        ]);

        return $project->fresh(Project::WITH_PRESENT);
    }

    public function addAttachment(int $projectId, array $data): ProjectAttachment
    {
        return ProjectAttachment::query()->create(array_merge($data, ['project_id' => $projectId]));
    }

    public function findAttachment(int $projectId, int $attachmentId): ?ProjectAttachment
    {
        return ProjectAttachment::query()
            ->where('project_id', $projectId)
            ->where('id', $attachmentId)
            ->first();
    }

    public function attachmentExistsByFilePath(string $filePath): bool
    {
        return ProjectAttachment::query()->where('file_path', $filePath)->exists();
    }

    public function updateAttachment(ProjectAttachment $attachment, array $data): ProjectAttachment
    {
        $attachment->fill($data);
        $attachment->save();

        return $attachment->fresh('uploader');
    }

    public function deleteAttachment(ProjectAttachment $attachment): bool
    {
        return (bool) $attachment->delete();
    }

    public function listFolders(int $projectId, ?int $parentId): Collection
    {
        return ProjectFolder::query()
            ->with('creator')
            ->where('project_id', $projectId)
            ->where(fn ($q) => $parentId === null ? $q->whereNull('parent_id') : $q->where('parent_id', $parentId))
            ->orderBy('name')
            ->get();
    }

    public function findFolder(int $projectId, int $folderId): ?ProjectFolder
    {
        return ProjectFolder::query()
            ->where('project_id', $projectId)
            ->where('id', $folderId)
            ->first();
    }

    public function createFolder(int $projectId, array $data): ProjectFolder
    {
        return ProjectFolder::query()->create(array_merge($data, ['project_id' => $projectId]));
    }

    public function updateFolder(ProjectFolder $folder, array $data): ProjectFolder
    {
        $folder->fill($data);
        $folder->save();

        return $folder->fresh('creator');
    }

    public function deleteFolder(ProjectFolder $folder): bool
    {
        return (bool) $folder->delete();
    }

    public function listAttachmentsInFolder(int $projectId, ?int $folderId): Collection
    {
        return ProjectAttachment::query()
            ->with('uploader')
            ->where('project_id', $projectId)
            ->where(fn ($q) => $folderId === null ? $q->whereNull('folder_id') : $q->where('folder_id', $folderId))
            ->orderByDesc('created_at')
            ->get();
    }

    public function listAttachmentsInFolders(int $projectId, array $folderIds): Collection
    {
        if ($folderIds === []) {
            return collect();
        }

        return ProjectAttachment::query()
            ->where('project_id', $projectId)
            ->whereIn('folder_id', $folderIds)
            ->get();
    }

    public function sumAttachmentSizeInFolders(int $projectId, array $folderIds): int
    {
        if ($folderIds === []) {
            return 0;
        }

        return (int) ProjectAttachment::query()
            ->where('project_id', $projectId)
            ->whereIn('folder_id', $folderIds)
            ->sum('size_bytes');
    }

    public function descendantFolderIds(int $projectId, int $folderId): array
    {
        $ids = [$folderId];
        $frontier = [$folderId];

        while ($frontier !== []) {
            $children = ProjectFolder::query()
                ->where('project_id', $projectId)
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->all();
            $frontier = array_values(array_diff($children, $ids));
            foreach ($frontier as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function nextCode(): string
    {
        return DB::transaction(function () {
            $settings = ProjectSetting::query()->lockForUpdate()->first();
            if ($settings === null) {
                $settings = ProjectSetting::query()->create(ProjectSetting::defaultAttributes());
            }

            $counter = (int) $settings->code_counter;
            $code = $this->applyCodePattern($settings->code_pattern, $counter);

            $settings->update(['code_counter' => $counter + 1]);

            return $code;
        });
    }

    public function previewNextCode(): string
    {
        $settings = $this->getSettings();

        return $this->applyCodePattern($settings->code_pattern, (int) $settings->code_counter);
    }

    public function nextTaskCode(): string
    {
        return DB::transaction(function () {
            $settings = ProjectSetting::query()->lockForUpdate()->first();
            if ($settings === null) {
                $settings = ProjectSetting::query()->create(ProjectSetting::defaultAttributes());
            }

            $counter = (int) $settings->task_code_counter;
            $code = $this->applyCodePattern($settings->task_code_pattern, $counter);

            $settings->update(['task_code_counter' => $counter + 1]);

            return $code;
        });
    }

    public function previewNextTaskCode(): string
    {
        $settings = $this->getSettings();

        return $this->applyCodePattern($settings->task_code_pattern, (int) $settings->task_code_counter);
    }

    /**
     * Áp pattern mã dự án. Token hỗ trợ:
     *  - {count}        số đếm không đệm
     *  - {count:N}      số đếm đệm N chữ số 0
     *  - {date,"m/Y"}   ngày theo format PHP date(), cú pháp AMIS
     *  - {date:Y}       ngày theo format PHP date(), cú pháp cũ
     */
    private function applyCodePattern(string $pattern, int $counter): string
    {
        $now = Carbon::now();

        $result = preg_replace_callback('/\{date,"([^"]+)"\}/', function ($m) use ($now) {
            return $now->format($m[1]);
        }, $pattern) ?? $pattern;

        $result = preg_replace_callback("/\\{date,'([^']+)'\\}/", function ($m) use ($now) {
            return $now->format($m[1]);
        }, $result) ?? $result;

        $result = preg_replace_callback('/\{date:([^}]+)\}/', function ($m) use ($now) {
            return $now->format($m[1]);
        }, $result) ?? $result;

        $result = preg_replace_callback('/\{count:(\d+)\}/', function ($m) use ($counter) {
            return str_pad((string) $counter, (int) $m[1], '0', STR_PAD_LEFT);
        }, $result) ?? $result;

        return str_replace('{count}', (string) $counter, $result);
    }

    public function assignableUsersInDepartment(?int $departmentId): Collection
    {
        $query = User::query()
            ->select(['id', 'name', 'email', 'avatar_url', 'department_id', 'status'])
            ->with('department:id,name');

        if ($departmentId !== null) {
            $query->where('department_id', $departmentId);
        }

        return $query->orderBy('name')->get();
    }

    public function allUsers(): Collection
    {
        return User::query()
            ->select(['id', 'name', 'email', 'avatar_url', 'department_id', 'status'])
            ->with('department:id,name')
            ->orderBy('name')
            ->get();
    }

    public function findUser(int $id): ?User
    {
        return User::query()->with('department:id,name')->find($id);
    }

    public function forViewer(Builder $query, User $viewer): Builder
    {
        if ($this->seesEveryDepartment($viewer)) {
            return $query;
        }

        return $this->whereViewerDepartment($query, $viewer);
    }

    public function forAssignableTaskProject(Builder $query, User $viewer): Builder
    {
        if ($this->seesEveryDepartment($viewer)) {
            return $query;
        }

        return $this->whereViewerDepartment($query, $viewer);
    }

    /**
     * Xem mọi phòng ban: super admin thật, admin (project.*), giám đốc điều
     * hành (dashboard.view_company). PermissionService tôn trọng "xem thử",
     * nên đổi vai trò trưởng phòng không còn lộ dự án phòng khác.
     */
    private function seesEveryDepartment(User $viewer): bool
    {
        $permissions = app(PermissionService::class);

        return $permissions->allows($viewer, 'project.*')
            || $permissions->allows($viewer, 'dashboard.view_company');
    }

    /**
     * Dự án của đúng phòng ban viewer: sở hữu, phụ trách, hoặc được giao
     * thực hiện. Không mở theo tư cách cá nhân (thành viên, theo dõi, người
     * tạo) nếu dự án thuộc phòng ban khác.
     */
    private function whereViewerDepartment(Builder $query, User $viewer): Builder
    {
        $departmentId = $viewer->department_id ? (int) $viewer->department_id : null;
        if ($departmentId === null) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $sub) use ($departmentId) {
            $sub->where('owner_department_id', $departmentId)
                ->orWhere('lead_department_id', $departmentId)
                ->orWhere('executing_department_id', $departmentId)
                ->orWhereHas('executingDepartments', function (Builder $d) use ($departmentId) {
                    $d->where('departments.id', $departmentId);
                })
                ->orWhereHas('scopes', function (Builder $scope) use ($departmentId) {
                    $scope->where('scope_type', 'department')
                        ->where('department_id', $departmentId);
                });
        });
    }

    public function viewerCanAssignTo(User $viewer, Project $project): bool
    {
        return $this->forAssignableTaskProject(Project::query()->whereKey($project->id), $viewer)->exists();
    }

    public function searchAssignable(string $q, User $viewer, int $limit = 20, ?int $id = null): Collection
    {
        $query = Project::query()->with(['ownerDepartment:id,name', 'executingDepartment:id,name']);
        $this->forAssignableTaskProject($query, $viewer);

        if ($id !== null) {
            return $query->whereKey($id)->limit(1)->get();
        }

        $q = trim($q);
        if ($q !== '') {
            $query->where(function (Builder $sub) use ($q) {
                $sub->where('name', 'like', '%'.$q.'%')
                    ->orWhere('code', 'like', '%'.$q.'%');
            });
        }

        return $query->orderBy('name')->limit($limit)->get();
    }

    public function tabCounts(User $viewer): array
    {
        $tabs = ['in_progress', 'completed', 'on_hold', 'planning', 'cancelled', 'all', 'following', 'my_tasks', 'my_department'];

        $counts = [];
        foreach ($tabs as $tab) {
            $query = Project::query();
            $this->forViewer($query, $viewer);
            $this->applyTabFilter($query, $tab === 'all' ? null : $tab, $viewer);
            $counts[$tab] = $query->count();
        }

        return $counts;
    }

    // ---------- Follower (mục B) ----------

    public function addFollower(int $projectId, int $userId): void
    {
        DB::table((new ProjectFollower)->getTable())->updateOrInsert(
            ['project_id' => $projectId, 'user_id' => $userId],
            ['updated_at' => now(), 'created_at' => now()],
        );
    }

    public function removeFollower(int $projectId, int $userId): void
    {
        ProjectFollower::query()
            ->where('project_id', $projectId)
            ->where('user_id', $userId)
            ->delete();
    }

    public function isFollowing(int $projectId, int $userId): bool
    {
        return ProjectFollower::query()
            ->where('project_id', $projectId)
            ->where('user_id', $userId)
            ->exists();
    }

    // ---------- Allowlist tạo dự án (mục C) ----------

    public function isInCreatorAllowlist(int $userId): bool
    {
        return ProjectCreatorAllowlist::query()
            ->where('user_id', $userId)
            ->exists();
    }

    public function creatorAllowlistUsers(): array
    {
        return ProjectCreatorAllowlist::query()
            ->with('user:id,name,email,avatar_url')
            ->get()
            ->map(fn ($row) => $row->user ? [
                'id' => $row->user->id,
                'name' => $row->user->name,
                'email' => $row->user->email,
                'avatar_url' => $row->user->avatar_url,
            ] : null)
            ->filter()
            ->values()
            ->all();
    }

    public function replaceCreatorAllowlist(array $userIds): void
    {
        DB::transaction(function () use ($userIds) {
            ProjectCreatorAllowlist::query()->delete();

            foreach (array_unique($userIds) as $userId) {
                ProjectCreatorAllowlist::query()->create(['user_id' => $userId]);
            }
        });
    }

    // ---------- Cài đặt dự án (mục D) ----------

    public function getSettings(): ProjectSetting
    {
        return ProjectSetting::query()->firstOrCreate([], ProjectSetting::defaultAttributes());
    }

    public function updateSettings(array $data): ProjectSetting
    {
        $settings = $this->getSettings();
        $settings->update($data);

        return $settings->fresh();
    }

    public function autoStartEligibleProjects(): int
    {
        return Project::query()
            ->where('status', 'planning')
            ->whereNotNull('start_date')
            ->whereDate('start_date', '<=', Carbon::today())
            ->update(['status' => 'in_progress']);
    }

    // ---------- Nhãn (mục E) ----------

    public function allLabels(): array
    {
        return ProjectLabel::query()
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn (ProjectLabel $l) => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color])
            ->all();
    }

    public function findLabelByName(string $name): ?ProjectLabel
    {
        return ProjectLabel::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
    }

    public function createLabel(array $data): ProjectLabel
    {
        return ProjectLabel::query()->create($data);
    }

    // ---------- Loại dự án (mục A) ----------

    public function allTypes(): array
    {
        return ProjectType::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ProjectType $t) => ['id' => $t->id, 'name' => $t->name])
            ->all();
    }

    public function findTypeByName(string $name): ?ProjectType
    {
        return ProjectType::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
    }

    public function createType(array $data): ProjectType
    {
        return ProjectType::query()->create($data);
    }

    public function listQuickItems(int $projectId, ?string $kind = null): Collection
    {
        $query = ProjectQuickItem::query()
            ->where('project_id', $projectId)
            ->orderByDesc('id');

        if ($kind !== null && $kind !== '') {
            $query->where('kind', $kind);
        }

        return $query->get();
    }

    public function createQuickItem(int $projectId, array $data): ProjectQuickItem
    {
        return ProjectQuickItem::query()->create([
            'project_id' => $projectId,
            'kind' => $data['kind'],
            'title' => $data['title'],
            'payload' => $data['payload'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /**
     * Đếm project_quick_items theo kind — CHỈ còn ý nghĩa cho baseline/
     * signature (Project Giai đoạn 2 trở đi). Không còn tính work_items ở
     * đây — xem ProjectService::countWorkItems(), đọc từ bảng tasks thật
     * qua TaskRepositoryInterface::countByProject() (QD8, tránh
     * ProjectRepository phụ thuộc TaskRepositoryInterface — không cùng
     * Repository gọi chéo Repository khác).
     */
    public function countQuickItemsByKind(int $projectId): array
    {
        $rows = ProjectQuickItem::query()
            ->where('project_id', $projectId)
            ->selectRaw('kind, COUNT(*) as total')
            ->groupBy('kind')
            ->pluck('total', 'kind')
            ->all();

        $counts = [];
        foreach (ProjectQuickItem::KINDS as $kind) {
            $counts[$kind] = (int) ($rows[$kind] ?? 0);
        }

        return $counts;
    }
}
