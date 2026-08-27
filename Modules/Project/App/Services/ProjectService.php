<?php

namespace Modules\Project\App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Services\PermissionService;
use Modules\Project\App\Exceptions\ProjectOwnerDepartmentMissing;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\ProjectAttachment;
use Modules\Project\App\Models\ProjectLabel;
use Modules\Project\App\Models\ProjectScope;
use Modules\Project\App\Models\ProjectSetting;
use Modules\Project\App\Repositories\Contracts\ProjectRepositoryInterface;

/**
 * Business logic của module Project.
 *
 * KHÔNG tính toán evaluation_score / progress ở đây — cột evaluation_score
 * chỉ để trống, sẽ tổng hợp từ Task ở giai đoạn sau.
 */
class ProjectService
{
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(
        private readonly ProjectRepositoryInterface $projects,
        private readonly PermissionService $permissions,
    ) {}

    /** @param  array<string, mixed>  $filters */
    public function paginate(array $filters, int $perPage, int $page, User $viewer): LengthAwarePaginator
    {
        return $this->projects->paginate($filters, $perPage, $page, $viewer);
    }

    public function find(int $id): ?Project
    {
        return $this->projects->find($id);
    }

    /** Có quyền toàn cục bypass mọi bộ lọc phòng ban (super_admin hoặc được cấp project.* / '*'). */
    public function hasGlobalBypass(User $user): bool
    {
        return $user->isSuperAdmin() || $this->permissions->allows($user, 'project.*');
    }

    /** true nếu user có quyền tạo dự án — role sẵn có 'project.create' HOẶC nằm trong allowlist mở rộng (mục C). */
    public function userCanCreate(User $user): bool
    {
        if ($this->permissions->allows($user, 'project.create')) {
            return true;
        }

        return $this->projects->isInCreatorAllowlist($user->id);
    }

    /** true nếu user được phép sửa executing_department_id của dự án này. */
    public function userCanManageDepartment(User $user, Project $project): bool
    {
        if ($this->hasGlobalBypass($user)) {
            return true;
        }

        return $this->permissions->allows($user, 'project.manage_department', 'department', $project->owner_department_id)
            || $this->permissions->allows($user, 'project.update_department', 'department', $project->owner_department_id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Project|array{error: string}
     *
     * @throws ProjectOwnerDepartmentMissing
     */
    public function create(array $data, User $creator): Project|array
    {
        $error = $this->validateDateRange($data);
        if ($error !== null) {
            return ['error' => $error];
        }

        if ($creator->department_id === null) {
            throw new ProjectOwnerDepartmentMissing;
        }

        $code = $this->projects->nextCode();

        $project = $this->projects->create([
            'code' => $code,
            'type' => $data['type'],
            'name' => trim($data['name']),
            'lead_user_id' => $data['lead_user_id'] ?? null,
            'owner_department_id' => $creator->department_id,
            'executing_department_id' => $data['executing_department_id'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'progress_method' => $data['progress_method'] ?? 'average',
            'status' => $data['status'] ?? 'planning',
            'importance' => $data['importance'] ?? 'medium',
            'description' => $data['description'] ?? null,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);

        $project = $this->projects->replaceScopes($project, $data['scopes'] ?? []);
        $project = $this->projects->replaceMembers($project, $this->normalizeUserIds($data['member_ids'] ?? []));
        $project = $this->projects->replaceLabels($project, $this->normalizeUserIds($data['label_ids'] ?? []));

        return $this->projects->replaceFollowers($project, $this->normalizeUserIds($data['follower_ids'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Project|array{error: string}
     */
    public function update(Project $project, array $data, User $updater): Project|array
    {
        $merged = array_merge([
            'start_date' => $project->start_date?->toDateString(),
            'end_date' => $project->end_date?->toDateString(),
        ], $data);

        $error = $this->validateDateRange($merged);
        if ($error !== null) {
            return ['error' => $error];
        }

        $payload = ['updated_by' => $updater->id];

        foreach ([
            'type', 'name', 'lead_user_id', 'start_date', 'end_date',
            'progress_method', 'status', 'importance', 'description',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $field === 'name' ? trim($data[$field]) : $data[$field];
            }
        }

        // owner_department_id KHÔNG BAO GIỜ sửa được qua update() — chỉ set 1 lần lúc create().
        // executing_department_id chỉ áp dụng nếu người sửa đủ quyền quản lý phòng ban.
        if (array_key_exists('executing_department_id', $data) && $this->userCanManageDepartment($updater, $project)) {
            $payload['executing_department_id'] = $data['executing_department_id'];
        }

        $updated = $this->projects->update($project, $payload);

        if (array_key_exists('scopes', $data)) {
            $updated = $this->projects->replaceScopes($updated, $data['scopes'] ?? []);
        }

        if (array_key_exists('member_ids', $data)) {
            $updated = $this->projects->replaceMembers($updated, $this->normalizeUserIds($data['member_ids'] ?? []));
        }

        if (array_key_exists('label_ids', $data)) {
            $updated = $this->projects->replaceLabels($updated, $this->normalizeUserIds($data['label_ids'] ?? []));
        }

        if (array_key_exists('follower_ids', $data)) {
            $updated = $this->projects->replaceFollowers($updated, $this->normalizeUserIds($data['follower_ids'] ?? []));
        }

        return $updated;
    }

    public function delete(Project $project): bool
    {
        foreach ($project->attachments as $attachment) {
            $this->deletePhysicalFile($attachment);
        }

        if ($project->avatar_path) {
            Storage::disk('public')->delete($project->avatar_path);
        }

        return $this->projects->delete($project);
    }

    /**
     * @return ProjectAttachment|array{error: string}
     */
    public function uploadAttachment(
        Project $project,
        ?UploadedFile $file,
        ?string $url,
        int $uploadedBy,
    ): ProjectAttachment|array {
        if (! $file && ! $url) {
            return ['error' => 'Cần chọn file hoặc nhập link Google Drive.'];
        }

        if ($file) {
            $path = $file->store('project/'.$project->id, 'public');
            $isImage = in_array($file->getMimeType(), self::IMAGE_MIMES, true);

            return $this->projects->addAttachment($project->id, [
                'kind' => $isImage ? 'image' : 'file',
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'uploaded_by' => $uploadedBy,
            ]);
        }

        return $this->projects->addAttachment($project->id, [
            'kind' => 'drive_link',
            'url' => $url,
            'uploaded_by' => $uploadedBy,
        ]);
    }

    /** @return array{error: string}|null */
    public function destroyAttachment(Project $project, int $attachmentId): ?array
    {
        $attachment = $this->projects->findAttachment($project->id, $attachmentId);
        if ($attachment === null) {
            return ['error' => 'Không tìm thấy tệp đính kèm.'];
        }

        $this->deletePhysicalFile($attachment);
        $this->projects->deleteAttachment($attachment);

        return null;
    }

    /**
     * Cập nhật ảnh đại diện dự án — xoá ảnh cũ (nếu có) rồi lưu ảnh mới.
     */
    public function updateAvatar(Project $project, UploadedFile $file, int $updatedBy): Project
    {
        if ($project->avatar_path) {
            Storage::disk('public')->delete($project->avatar_path);
        }

        $path = $file->store('project/'.$project->id.'/avatar', 'public');

        return $this->projects->update($project, [
            'avatar_path' => $path,
            'updated_by' => $updatedBy,
        ]);
    }

    public function assignableUsers(User $currentUser): Collection
    {
        if ($currentUser->isSuperAdmin()) {
            return $this->projects->allUsers();
        }

        return $this->projects->assignableUsersInDepartment($currentUser->department_id);
    }

    // ---------- Theo dõi dự án (mục B) ----------

    public function follow(Project $project, User $user): void
    {
        $this->projects->addFollower($project->id, $user->id);
    }

    public function unfollow(Project $project, User $user): void
    {
        $this->projects->removeFollower($project->id, $user->id);
    }

    public function isFollowing(Project $project, User $user): bool
    {
        return $this->projects->isFollowing($project->id, $user->id);
    }

    // ---------- Tabs (mục F) ----------

    /** @return array<string, int> */
    public function tabCounts(User $viewer): array
    {
        return $this->projects->tabCounts($viewer);
    }

    // ---------- Allowlist tạo dự án (mục C) ----------

    /** @return list<array{id:int,name:string,email:string|null,avatar_url:string|null}> */
    public function creatorAllowlistUsers(): array
    {
        return $this->projects->creatorAllowlistUsers();
    }

    /** @param  list<int>  $userIds */
    public function replaceCreatorAllowlist(array $userIds): void
    {
        $this->projects->replaceCreatorAllowlist($this->normalizeUserIds($userIds));
    }

    // ---------- Cài đặt dự án (mục D) ----------

    /** @return array<string, mixed> */
    public function presentSettings(): array
    {
        $settings = $this->projects->getSettings();

        return [
            'code_pattern' => $settings->code_pattern,
            'code_counter' => $settings->code_counter,
            'next_code_preview' => $this->projects->previewNextCode(),
            'auto_start_on_begin_date' => (bool) $settings->auto_start_on_begin_date,
            'shift_task_dates_with_project' => (bool) $settings->shift_task_dates_with_project,
            'hide_cross_tasks_from_assignees' => (bool) $settings->hide_cross_tasks_from_assignees,
            'hide_child_tasks_from_followers' => (bool) $settings->hide_child_tasks_from_followers,
            'constrain_task_dates_to_project' => (bool) $settings->constrain_task_dates_to_project,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSettings(array $data): ProjectSetting
    {
        return $this->projects->updateSettings($data);
    }

    /** Chạy bởi AutoStartProjectsCommand — chỉ chuyển trạng thái nếu setting đang bật. */
    public function autoStartEligibleProjects(): int
    {
        $settings = $this->projects->getSettings();
        if (! $settings->auto_start_on_begin_date) {
            return 0;
        }

        return $this->projects->autoStartEligibleProjects();
    }

    // ---------- Nhãn (mục E) ----------

    /** @return list<array{id:int,name:string,color:string}> */
    public function allLabels(): array
    {
        return $this->projects->allLabels();
    }

    /**
     * @return ProjectLabel|array{error: string}
     */
    public function createLabel(string $name, string $color, int $createdBy): ProjectLabel|array
    {
        $name = trim($name);
        if ($name === '') {
            return ['error' => 'Tên nhãn không được để trống.'];
        }

        if ($this->projects->findLabelByName($name) !== null) {
            return ['error' => 'Nhãn này đã tồn tại.'];
        }

        return $this->projects->createLabel([
            'name' => $name,
            'color' => in_array($color, ProjectLabel::COLORS, true) ? $color : 'primary',
            'created_by' => $createdBy,
        ]);
    }

    // ---------- Present ----------

    /** @return array<string, mixed> */
    public function present(Project $project, ?User $viewer = null): array
    {
        return [
            'id' => $project->id,
            'code' => $project->code,
            'type' => $project->type,
            'name' => $project->name,
            'lead_user_id' => $project->lead_user_id,
            'lead' => $this->presentUser($project->lead),
            'owner_department' => $this->presentDepartment($project->ownerDepartment),
            'executing_department' => $this->presentDepartment($project->executingDepartment),
            'start_date' => $project->start_date?->toDateString(),
            'end_date' => $project->end_date?->toDateString(),
            'duration_days' => $this->durationDays($project),
            'progress_method' => $project->progress_method,
            'status' => $project->status,
            'importance' => $project->importance,
            'description' => $project->description,
            'avatar_path' => $project->avatar_path,
            'avatar_url' => $project->avatar_path ? Storage::disk('public')->url($project->avatar_path) : null,
            'evaluation_score' => $project->evaluation_score !== null ? (float) $project->evaluation_score : null,
            'progress_percent' => null, // Chưa có Task — luôn null ở giai đoạn 1.
            'scopes' => $project->scopes->map(fn (ProjectScope $s) => [
                'id' => $s->id,
                'scope_type' => $s->scope_type,
                'department' => $s->department ? ['id' => $s->department->id, 'name' => $s->department->name] : null,
                'weight_percent' => (float) $s->weight_percent,
            ])->values()->all(),
            'members' => $project->members->map(fn (User $u) => $this->presentUser($u))->values()->all(),
            'followers' => $project->followers->map(fn (User $u) => $this->presentUser($u))->values()->all(),
            'labels' => $project->labels->map(fn (ProjectLabel $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'color' => $l->color,
            ])->values()->all(),
            'attachments' => $project->attachments->map(fn (ProjectAttachment $a) => $this->presentAttachment($a))->values()->all(),
            'is_following' => $viewer ? $this->isFollowing($project, $viewer) : false,
            'created_by' => $project->created_by,
            'updated_by' => $project->updated_by,
            'creator' => $this->presentUser($project->creator),
            'updater' => $this->presentUser($project->updater),
            'created_at' => $project->created_at?->toIso8601String(),
            'updated_at' => $project->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function presentAttachment(ProjectAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'kind' => $attachment->kind,
            'file_path' => $attachment->file_path,
            'file_url' => $attachment->file_path ? Storage::disk('public')->url($attachment->file_path) : null,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size_bytes' => $attachment->size_bytes,
            'url' => $attachment->url,
            'uploaded_by' => $attachment->uploaded_by,
            'uploader' => $this->presentUser($attachment->uploader),
            'created_at' => $attachment->created_at?->toIso8601String(),
        ];
    }

    private function durationDays(Project $project): ?int
    {
        if (! $project->start_date || ! $project->end_date) {
            return null;
        }

        return $project->start_date->diffInDays($project->end_date) + 1;
    }

    /** @param  array<string, mixed>  $data */
    private function validateDateRange(array $data): ?string
    {
        $start = $data['start_date'] ?? null;
        $end = $data['end_date'] ?? null;

        if ($start && $end && $end < $start) {
            return 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.';
        }

        return null;
    }

    /** @return list<int> */
    private function normalizeUserIds(array $rawIds): array
    {
        return array_values(array_unique(array_map('intval', $rawIds)));
    }

    private function deletePhysicalFile(ProjectAttachment $attachment): void
    {
        if ($attachment->file_path) {
            Storage::disk('public')->delete($attachment->file_path);
        }
    }

    /** @return array{id: int, name: string, email: string|null, avatar_url: string|null}|null */
    private function presentUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
        ];
    }

    /** @return array{id: int, name: string}|null */
    private function presentDepartment(?Department $department): ?array
    {
        if ($department === null) {
            return null;
        }

        return ['id' => $department->id, 'name' => $department->name];
    }
}
