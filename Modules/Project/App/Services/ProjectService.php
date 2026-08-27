<?php

namespace Modules\Project\App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Services\PermissionService;
use Modules\Project\App\Enums\ProjectEnums;
use Modules\Project\App\Exceptions\ProjectOwnerDepartmentMissing;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\ProjectAttachment;
use Modules\Project\App\Models\ProjectLabel;
use Modules\Project\App\Models\ProjectScope;
use Modules\Project\App\Models\ProjectSetting;
use Modules\Project\App\Models\ProjectType;
use Modules\Project\App\Repositories\Contracts\ProjectRepositoryInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        private readonly ProjectExcelExporter $exporter,
        private readonly ProjectExcelImporter $importer,
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

    /**
     * true nếu user được chọn thẳng "phòng ban giao" (owner_department_id)
     * lúc tạo dự án thay vì bị khoá theo phòng ban của chính mình — chỉ
     * super_admin và giám đốc điều hành (mục C).
     */
    public function userCanChooseOwnerDepartment(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole('director_officer');
    }

    /**
     * Xuất danh sách dự án ra Excel theo đúng bộ lọc đang dùng trên trang danh sách.
     *
     * @param  array<string, mixed>  $filters
     * @param  list<string>|null  $columnKeys  null = xuất đủ mọi cột (dùng làm file mẫu nhập lại)
     */
    public function export(array $filters, ?array $columnKeys, User $exportedBy): BinaryFileResponse
    {
        $projects = $this->projects->forExport($filters, $exportedBy);
        $rows = $projects->map(fn (Project $p) => $this->presentForExport($p))->values()->all();
        $filename = 'Du_an_'.now()->format('Ymd_His').'.xlsx';

        return $this->exporter->download($rows, $exportedBy, $filename, $columnKeys);
    }

    /**
     * Đọc + xem trước file Excel: chuẩn hoá dữ liệu, phát hiện đầy đủ vấn đề của
     * từng dòng — KHÔNG ghi DB. Dòng có Mã dự án khớp dự án đang tồn tại được
     * đánh dấu action=update (sửa dự án đó); còn lại action=create.
     *
     * @return array{rows: list<array<string, mixed>>}
     */
    public function previewImport(UploadedFile $file, User $viewer): array
    {
        return $this->importer->preview($file, $viewer);
    }

    /**
     * Re-resolve 1 dòng đơn sau khi người dùng sửa lỗi tại chỗ trong bảng xem
     * trước — không đọc lại file, chỉ chuẩn hoá + validate lại các giá trị mới.
     *
     * @param  array<string, mixed>  $cells
     * @return array<string, mixed>
     */
    public function resolveImportRow(array $cells, User $viewer): array
    {
        return $this->importer->resolveSingle($cells, $viewer);
    }

    /**
     * Ghi DB các dòng đã được xác nhận từ bước preview — KHÔNG đọc lại file.
     * Dòng action=update sửa đúng dự án đã đối chiếu qua Mã dự án ở bước
     * preview (chỉ ghi đè field nào Excel có giá trị — provided_fields); dòng
     * action=create tạo mới như trước. Tạo/sửa được dòng nào lưu dòng đó — 1
     * dòng lỗi không làm rollback các dòng khác.
     *
     * @param  list<array<string, mixed>>  $validatedRows
     * @return array{created: list<array<string, mixed>>, updated: list<array<string, mixed>>, errors: list<array{row: int, message: string}>}
     */
    public function confirmImport(array $validatedRows, User $importedBy): array
    {
        $created = [];
        $updated = [];
        $errors = [];

        foreach ($validatedRows as $row) {
            $isUpdate = ($row['action'] ?? 'create') === 'update';

            try {
                $project = DB::transaction(function () use ($row, $importedBy, $isUpdate) {
                    if ($isUpdate) {
                        $projectId = (int) ($row['project_id'] ?? 0);
                        $existing = $this->projects->find($projectId);
                        if ($existing === null) {
                            throw new \RuntimeException('Dự án cần cập nhật không còn tồn tại.');
                        }
                        if (! $this->userCanManageDepartment($importedBy, $existing)) {
                            throw new \RuntimeException('Bạn không có quyền sửa dự án này.');
                        }

                        $payload = $this->onlyProvidedFields($row);
                        $result = $this->update($existing, $payload, $importedBy);
                    } else {
                        $result = $this->create($row, $importedBy);
                    }

                    if (is_array($result)) {
                        throw new \RuntimeException($result['error']);
                    }

                    return $result;
                });

                if ($isUpdate) {
                    $updated[] = $this->present($project, $importedBy);
                } else {
                    $created[] = $this->present($project, $importedBy);
                }
            } catch (ProjectOwnerDepartmentMissing $e) {
                $errors[] = ['row' => $row['row'] ?? 0, 'message' => $e->getMessage()];
            } catch (\Throwable $e) {
                $verb = $isUpdate ? 'Không cập nhật được: ' : 'Không tạo được: ';
                $errors[] = ['row' => $row['row'] ?? 0, 'message' => $verb.$e->getMessage()];
            }
        }

        usort($errors, fn ($a, $b) => $a['row'] <=> $b['row']);

        return ['created' => $created, 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * Lọc payload 1 dòng import xuống chỉ field nào Excel thực sự có giá trị
     * (row['provided_fields'], sinh bởi ProjectExcelImporter::resolveRow()) —
     * để update() giữ nguyên field còn lại thay vì ghi đè bằng giá trị rỗng.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function onlyProvidedFields(array $row): array
    {
        $provided = $row['provided_fields'] ?? [];

        return array_intersect_key($row, array_flip($provided));
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

        // Super admin / giám đốc điều hành có thể không thuộc phòng ban nào,
        // hoặc muốn tạo dự án thay mặt phòng ban khác — cho phép chọn thẳng
        // owner_department_id trong request (mục C). Role khác vẫn bị khoá
        // theo phòng ban của chính mình, bỏ qua field này nếu có gửi lên.
        $ownerDepartmentId = $creator->department_id;
        if (($data['owner_department_id'] ?? null) && $this->userCanChooseOwnerDepartment($creator)) {
            $ownerDepartmentId = (int) $data['owner_department_id'];
        }

        if ($ownerDepartmentId === null) {
            throw new ProjectOwnerDepartmentMissing;
        }

        $code = $this->projects->nextCode();

        $settings = $this->projects->getSettings();
        $executingIds = $this->normalizeDepartmentIds(
            $data['executing_department_ids'] ?? (isset($data['executing_department_id']) ? [$data['executing_department_id']] : []),
        );

        $this->ensureType($data['type'], $creator->id);

        $project = $this->projects->create([
            'code' => $code,
            'type' => trim($data['type']),
            'name' => trim($data['name']),
            'lead_user_id' => $data['lead_user_id'] ?? null,
            'lead_department_id' => $data['lead_department_id'] ?? null,
            'owner_department_id' => $ownerDepartmentId,
            'executing_department_id' => $executingIds[0] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'progress_method' => $data['progress_method'] ?? $settings->default_progress_method ?? 'average',
            'status' => $data['status'] ?? 'planning',
            'importance' => $data['importance'] ?? 'important',
            'description' => $data['description'] ?? null,
            'shift_task_dates_with_project' => (bool) ($data['shift_task_dates_with_project'] ?? $settings->shift_task_dates_with_project),
            'hide_cross_tasks_from_assignees' => (bool) ($data['hide_cross_tasks_from_assignees'] ?? $settings->hide_cross_tasks_from_assignees),
            'hide_child_tasks_from_followers' => (bool) ($data['hide_child_tasks_from_followers'] ?? $settings->hide_child_tasks_from_followers),
            'constrain_task_dates_to_project' => (bool) ($data['constrain_task_dates_to_project'] ?? $settings->constrain_task_dates_to_project),
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);

        $project = $this->projects->replaceExecutingDepartments($project, $executingIds);
        $project = $this->projects->replaceScopes($project, $this->normalizeScopes($data['scopes'] ?? []));
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
            'type', 'name', 'lead_user_id', 'lead_department_id', 'start_date', 'end_date',
            'progress_method', 'status', 'importance', 'description',
            'shift_task_dates_with_project', 'hide_cross_tasks_from_assignees',
            'hide_child_tasks_from_followers', 'constrain_task_dates_to_project',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = in_array($field, ['name', 'type'], true) ? trim($data[$field]) : $data[$field];
            }
        }

        if (array_key_exists('type', $data)) {
            $this->ensureType($data['type'], $updater->id);
        }

        // owner_department_id KHÔNG BAO GIỜ sửa được qua update() — chỉ set 1 lần lúc create().
        // Phòng ban thực hiện chỉ áp dụng nếu người sửa đủ quyền quản lý phòng ban.
        $canManageDept = $this->userCanManageDepartment($updater, $project);
        if ($canManageDept && (array_key_exists('executing_department_ids', $data) || array_key_exists('executing_department_id', $data))) {
            $executingIds = $this->normalizeDepartmentIds(
                $data['executing_department_ids'] ?? (isset($data['executing_department_id']) ? [$data['executing_department_id']] : []),
            );
            $payload['executing_department_id'] = $executingIds[0] ?? null;
        }

        $updated = $this->projects->update($project, $payload);

        if ($canManageDept && (array_key_exists('executing_department_ids', $data) || array_key_exists('executing_department_id', $data))) {
            $updated = $this->projects->replaceExecutingDepartments(
                $updated,
                $this->normalizeDepartmentIds(
                    $data['executing_department_ids'] ?? (isset($data['executing_department_id']) ? [$data['executing_department_id']] : []),
                ),
            );
        }

        if (array_key_exists('scopes', $data)) {
            $updated = $this->projects->replaceScopes($updated, $this->normalizeScopes($data['scopes'] ?? []));
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

    /**
     * Gỡ ảnh đại diện dự án (ví dụ upload nhầm) — xoá file vật lý và
     * dọn avatar_path về null.
     */
    public function deleteAvatar(Project $project, int $updatedBy): Project
    {
        if ($project->avatar_path) {
            Storage::disk('public')->delete($project->avatar_path);
        }

        return $this->projects->update($project, [
            'avatar_path' => null,
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
            'default_progress_method' => $settings->default_progress_method ?: 'average',
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

    // ---------- Loại dự án (mục A) ----------

    /** @return list<array{value:string,label:string}> */
    public function allTypes(): array
    {
        return array_map(
            fn (array $t) => ['value' => $t['name'], 'label' => $t['name']],
            $this->projects->allTypes(),
        );
    }

    /**
     * @return ProjectType|array{error: string}
     */
    public function createType(string $name, int $createdBy): ProjectType|array
    {
        $name = trim($name);
        if ($name === '') {
            return ['error' => 'Tên loại dự án không được để trống.'];
        }

        if ($this->projects->findTypeByName($name) !== null) {
            return ['error' => 'Loại dự án này đã tồn tại.'];
        }

        return $this->projects->createType([
            'name' => $name,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Đảm bảo loại dự án đã có trong danh mục project_types — tự tạo nếu là
     * tên hoàn toàn mới (áp dụng cho mọi đường ghi dữ liệu: form UI, nhập
     * Excel) để "type" luôn tái dùng được cho các dự án sau, không tạo trùng
     * lặp khác hoa/thường.
     */
    private function ensureType(string $name, int $createdBy): void
    {
        $name = trim($name);
        if ($name === '' || $this->projects->findTypeByName($name) !== null) {
            return;
        }

        $this->projects->createType(['name' => $name, 'created_by' => $createdBy]);
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
            'lead_department' => $this->presentDepartment($project->leadDepartment),
            'owner_department' => $this->presentDepartment($project->ownerDepartment),
            'executing_department' => $this->presentDepartment($project->executingDepartment),
            'executing_departments' => $project->executingDepartments
                ->map(fn (Department $d) => $this->presentDepartment($d))
                ->filter()
                ->values()
                ->all(),
            'start_date' => $project->start_date?->toDateString(),
            'end_date' => $project->end_date?->toDateString(),
            'duration_days' => $this->durationDays($project),
            'progress_method' => $project->progress_method,
            'status' => $project->status,
            'importance' => $project->importance,
            'description' => $project->description,
            'shift_task_dates_with_project' => (bool) $project->shift_task_dates_with_project,
            'hide_cross_tasks_from_assignees' => (bool) $project->hide_cross_tasks_from_assignees,
            'hide_child_tasks_from_followers' => (bool) $project->hide_child_tasks_from_followers,
            'constrain_task_dates_to_project' => (bool) $project->constrain_task_dates_to_project,
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
    private function presentForExport(Project $project): array
    {
        return [
            'code' => $project->code,
            'name' => $project->name,
            'type_label' => $project->type,
            'owner_department_name' => $project->ownerDepartment?->name ?? '',
            'executing_department_name' => $project->executingDepartments->pluck('name')->filter()->implode('; ')
                ?: ($project->executingDepartment?->name ?? ''),
            'lead_email' => $project->lead?->email ?? '',
            'member_emails' => $project->members->pluck('email')->filter()->implode('; '),
            'follower_emails' => $project->followers->pluck('email')->filter()->implode('; '),
            'label_names' => $project->labels->pluck('name')->implode('; '),
            'status_label' => ProjectEnums::STATUS_LABELS[$project->status] ?? $project->status,
            'importance_label' => ProjectEnums::IMPORTANCE_LABELS[$project->importance] ?? $project->importance,
            'start_date' => $project->start_date?->format('d/m/Y') ?? '',
            'end_date' => $project->end_date?->format('d/m/Y') ?? '',
            'progress_method_label' => ProjectEnums::PROGRESS_METHOD_LABELS[$project->progress_method] ?? $project->progress_method,
            'progress' => '',
            'evaluation_score' => $project->evaluation_score !== null ? (string) $project->evaluation_score : '',
            'description' => $project->description ?? '',
            'creator_name' => $project->creator?->name ?? '',
            'created_at' => $project->created_at?->format('d/m/Y H:i') ?? '',
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
        return array_values(array_unique(array_filter(array_map('intval', $rawIds))));
    }

    /** @return list<int> */
    private function normalizeDepartmentIds(array $rawIds): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $rawIds))));
    }

    /**
     * Mỗi dự án chỉ 1 phạm vi — lấy dòng đầu, mặc định tỷ trọng 100%.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function normalizeScopes(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $row = $rows[0];
        $scopeType = $row['scope_type'] ?? null;
        if (! is_string($scopeType) || $scopeType === '') {
            return [];
        }

        return [[
            'scope_type' => $scopeType,
            'department_id' => ($scopeType === 'department') ? ($row['department_id'] ?? null) : null,
            'weight_percent' => $row['weight_percent'] ?? 100,
        ]];
    }

    private function deletePhysicalFile(ProjectAttachment $attachment): void
    {
        if ($attachment->file_path) {
            Storage::disk('public')->delete($attachment->file_path);
        }
    }

    /** @return array{id: int, name: string, email: string|null, avatar_url: string|null, status: string, department: array{id: int, name: string}|null}|null */
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
            'status' => $user->status,
            'department' => $this->presentDepartment($user->department),
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
