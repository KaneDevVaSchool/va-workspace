<?php

namespace Modules\Project\App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Services\NotificationService;
use Modules\Identity\App\Services\PermissionService;
use Modules\Project\App\Enums\TaskEnums;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Models\TaskScore;
use Modules\Project\App\Repositories\Contracts\ProjectRepositoryInterface;
use Modules\Project\App\Repositories\Contracts\TaskRepositoryInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Business logic của entity Task (Project Giai đoạn 2 — WBS đa cấp).
 *
 * Quyền xem Task = quyền xem Project chứa nó — KHÔNG viết lại RBAC riêng
 * cho Task, tái dùng ProjectRepositoryInterface::forViewer().
 */
class TaskService
{
    /** Ngưỡng cảnh báo mềm độ sâu WBS — không chặn cứng (docs/VA_WORKSPACE_OVERVIEW.md §20.9). */
    private const DEPTH_WARNING_THRESHOLD = 6;

    public function __construct(
        private readonly TaskRepositoryInterface $tasks,
        private readonly ProjectRepositoryInterface $projects,
        private readonly PermissionService $permissions,
        private readonly TaskExcelExporter $exporter,
        private readonly TaskExcelImporter $importer,
        private readonly NotificationService $notifications,
    ) {}

    /** @param  array<string, mixed>  $filters */
    public function paginate(array $filters, int $perPage, int $page, User $viewer): LengthAwarePaginator
    {
        // task.view_assigned (không có task.view/task.*) → chỉ xem Task giao cho chính mình,
        // bất kể query gửi gì.
        if (! $this->permissions->allows($viewer, 'task.view') && ! $this->permissions->allows($viewer, 'task.*')) {
            $filters['assignee_id'] = $viewer->id;
        }

        $allowedProjectIds = $this->projects->forViewer(Project::query(), $viewer)->pluck('id')->all();

        return $this->tasks->paginate($filters, $perPage, $page, $allowedProjectIds, $viewer);
    }

    /**
     * Xuất danh sách công việc ra Excel theo đúng bộ lọc đang dùng trên
     * trang "Tất cả công việc" (PR8).
     *
     * @param  array<string, mixed>  $filters
     * @param  list<string>|null  $columnKeys  null = xuất đủ mọi cột (dùng làm file mẫu nhập lại)
     */
    public function export(array $filters, ?array $columnKeys, User $exportedBy): BinaryFileResponse
    {
        if (! $this->permissions->allows($exportedBy, 'task.view') && ! $this->permissions->allows($exportedBy, 'task.*')) {
            $filters['assignee_id'] = $exportedBy->id;
        }

        $allowedProjectIds = $this->projects->forViewer(Project::query(), $exportedBy)->pluck('id')->all();
        $tasks = $this->tasks->forExport($filters, $allowedProjectIds, $exportedBy);
        $rows = $tasks->map(fn (Task $t) => $this->presentForExport($t))->values()->all();
        $filename = 'Cong_viec_'.now()->format('Ymd_His').'.xlsx';

        return $this->exporter->download($rows, $exportedBy, $filename, $columnKeys);
    }

    /**
     * Đọc + xem trước file Excel — KHÔNG ghi DB (PR8).
     *
     * @return array{rows: list<array<string, mixed>>}
     */
    public function previewImport(UploadedFile $file, User $viewer): array
    {
        return $this->importer->preview($file, $viewer);
    }

    /**
     * Re-resolve 1 dòng đơn sau khi người dùng sửa lỗi tại chỗ trong bảng
     * xem trước — không đọc lại file (PR8).
     *
     * @param  array<string, mixed>  $cells
     * @return array<string, mixed>
     */
    public function resolveImportRow(array $cells, User $viewer): array
    {
        return $this->importer->resolveSingle($cells, $viewer);
    }

    /**
     * Ghi DB các dòng đã được xác nhận từ bước preview — KHÔNG đọc lại
     * file. Frontend spread row.data lên top-level trước khi gửi (cùng
     * pattern ProjectList.vue::confirmImportRows(): {...r.data, action,
     * project_id, provided_fields, row}) — $row ở đây là field top-level,
     * KHÔNG lồng trong 'data'. Dòng action=update sửa đúng task đã đối
     * chiếu qua Mã công việc (chỉ ghi đè field nào Excel có giá trị —
     * provided_fields); dòng action=create tạo mới. Tạo/sửa được dòng nào
     * lưu dòng đó — 1 dòng lỗi không làm rollback các dòng khác (PR8).
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
                $task = DB::transaction(function () use ($row, $importedBy, $isUpdate) {
                    if ($isUpdate) {
                        $taskId = (int) ($row['task_id'] ?? 0);
                        $existing = $this->tasks->find($taskId);
                        if ($existing === null) {
                            throw new \RuntimeException('Công việc cần cập nhật không còn tồn tại.');
                        }

                        $payload = $this->onlyProvidedFields($row);
                        $result = $this->update($existing, $payload, $importedBy);
                    } else {
                        $projectId = $row['project_id'] ?? null;
                        if ($projectId === null) {
                            throw new \RuntimeException('Thiếu dự án đích.');
                        }
                        $project = $this->projects->find((int) $projectId);
                        if ($project === null) {
                            throw new \RuntimeException('Không tìm thấy dự án đích.');
                        }

                        $payload = $row;
                        unset(
                            $payload['project_id'], $payload['project_code'],
                            $payload['assignee_name'], $payload['manager_name'],
                            $payload['action'], $payload['provided_fields'], $payload['row'],
                            $payload['task_id'], $payload['code'],
                        );
                        $result = $this->create($project, $payload, $importedBy);
                    }

                    if (is_array($result)) {
                        throw new \RuntimeException($result['error']);
                    }

                    return $result;
                });

                if ($isUpdate) {
                    $updated[] = $this->present($task);
                } else {
                    $created[] = $this->present($task);
                }
            } catch (\Throwable $e) {
                $verb = $isUpdate ? 'Không cập nhật được: ' : 'Không tạo được: ';
                $errors[] = ['row' => $row['row'] ?? 0, 'message' => $verb.$e->getMessage()];
            }
        }

        usort($errors, fn ($a, $b) => $a['row'] <=> $b['row']);

        return ['created' => $created, 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * Lọc payload 1 dòng import xuống chỉ field nào Excel thực sự có giá
     * trị (row['provided_fields'], sinh bởi TaskExcelImporter::resolveRow())
     * — để update() giữ nguyên field còn lại thay vì ghi đè bằng rỗng.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function onlyProvidedFields(array $row): array
    {
        $provided = $row['provided_fields'] ?? [];

        return array_intersect_key($row, array_flip($provided));
    }

    private function presentForExport(Task $task): array
    {
        return [
            'code' => $task->code,
            'project_code' => $task->project?->code ?? '',
            'title' => $task->title,
            'type_label' => TaskEnums::TYPE_LABELS[$task->type] ?? $task->type,
            'status_label' => TaskEnums::STATUS_LABELS[$task->status] ?? $task->status,
            'priority_label' => $task->priority ? (TaskEnums::PRIORITY_LABELS[$task->priority] ?? $task->priority) : '',
            'assignee_email' => $task->assignee?->email ?? '',
            'manager_email' => $task->manager?->email ?? '',
            'start_date' => $task->start_date?->format('d/m/Y') ?? '',
            'end_date' => $task->end_date?->format('d/m/Y') ?? '',
            'progress_type_label' => TaskEnums::PROGRESS_TYPE_LABELS[$task->progress_type] ?? $task->progress_type,
            'progress_percent' => $task->progress_percent !== null ? (string) $task->progress_percent : '',
            'progress_number' => $task->progress_number !== null ? (string) $task->progress_number : '',
            'progress_total' => $task->progress_total !== null ? (string) $task->progress_total : '',
            'unit' => $task->unit ?? '',
            'estimated_hours' => $task->estimated_hours !== null ? (string) $task->estimated_hours : '',
            'weight' => $task->weight !== null ? (string) $task->weight : '',
            'description' => $task->description ?? '',
        ];
    }

    /** @return array<string, int> */
    public function tabCounts(User $viewer): array
    {
        $forceAssigneeId = null;
        if (! $this->permissions->allows($viewer, 'task.view') && ! $this->permissions->allows($viewer, 'task.*')) {
            $forceAssigneeId = $viewer->id;
        }

        $allowedProjectIds = $this->projects->forViewer(Project::query(), $viewer)->pluck('id')->all();

        return $this->tasks->tabCounts($allowedProjectIds, $forceAssigneeId, $viewer);
    }

    public function find(int $id): ?Task
    {
        return $this->tasks->find($id);
    }

    /**
     * Cây WBS đầy đủ của 1 project, kèm wbs_code tính runtime (không lưu
     * cột — docs/VA_WORKSPACE_OVERVIEW.md §16.1).
     *
     * @return list<array<string, mixed>>
     */
    public function treeForProject(Project $project): array
    {
        $flat = $this->tasks->flatForProject($project->id);

        $byParent = [];
        foreach ($flat as $task) {
            $key = $task->parent_id ?? 0;
            $byParent[$key][] = $task;
        }

        return $this->buildWbsTree($byParent, 0, '');
    }

    /**
     * @param  array<int, list<Task>>  $byParent
     * @return list<array<string, mixed>>
     */
    private function buildWbsTree(array $byParent, int $parentKey, string $parentWbs): array
    {
        $siblings = $byParent[$parentKey] ?? [];
        $result = [];

        foreach (array_values($siblings) as $index => $task) {
            $wbsCode = $parentWbs === '' ? (string) ($index + 1) : $parentWbs.'.'.($index + 1);
            $node = $this->present($task);
            $node['wbs_code'] = $wbsCode;
            $node['children'] = $this->buildWbsTree($byParent, $task->id, $wbsCode);
            $result[] = $node;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Task|array{error: string}
     */
    public function create(Project $project, array $data, User $creator): Task|array
    {
        $titles = $data['titles'] ?? null;
        if (is_array($titles) && $titles !== []) {
            $last = null;
            foreach ($titles as $title) {
                $last = $this->createSingle($project, array_merge($data, ['title' => $title]), $creator);
                if (is_array($last)) {
                    return $last;
                }
            }

            return $last;
        }

        return $this->createSingle($project, $data, $creator);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Task|array{error: string}
     */
    private function createSingle(Project $project, array $data, User $creator): Task|array
    {
        $parentId = $data['parent_id'] ?? null;
        if ($parentId !== null) {
            $parent = $this->tasks->find((int) $parentId);
            if ($parent === null || $parent->project_id !== $project->id) {
                return ['error' => 'Công việc cha không thuộc dự án này.'];
            }
        }

        unset($data['titles']);

        $data = $this->applyQuantityProgress($data);

        $payload = array_merge($data, [
            'project_id' => $project->id,
            'type' => $data['type'] ?? 'task',
            'status' => $data['status'] ?? 'not_started',
            'origin_department_id' => $project->owner_department_id,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);

        return $this->tasks->create($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Task|array{error: string}
     */
    public function update(Task $task, array $data, User $editor): Task|array
    {
        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            $newParentId = (int) $data['parent_id'];

            if ($newParentId === $task->id) {
                return ['error' => 'Không thể chọn chính công việc này làm công việc cha.'];
            }

            $descendantIds = $this->tasks->descendantIds($task->id);
            if (in_array($newParentId, $descendantIds, true)) {
                return ['error' => 'Không thể chọn công việc con của chính nó làm công việc cha.'];
            }

            $newParent = $this->tasks->find($newParentId);
            if ($newParent === null || $newParent->project_id !== $task->project_id) {
                return ['error' => 'Công việc cha không thuộc cùng dự án.'];
            }
        }

        $data = $this->applyQuantityProgress($data, $task);
        $data = $this->applyAcceptedTracking($task, $data);

        $data['updated_by'] = $editor->id;

        return $this->tasks->update($task, $data);
    }

    /**
     * Nhóm H — khi progress_type=quantity, tự tính progress_percent =
     * round(progress_number / progress_total * 100), ghi đè giá trị client
     * gửi (Request đã prohibited progress_percent trong trường hợp này nên
     * $data không có sẵn field đó — hàm này là nguồn thật duy nhất).
     * Không lưu trùng công thức — chỉ tính khi có đủ number + total > 0.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyQuantityProgress(array $data, ?Task $existing = null): array
    {
        $type = $data['progress_type'] ?? $existing?->progress_type ?? 'percent';
        if ($type !== 'quantity') {
            return $data;
        }

        $number = $data['progress_number'] ?? $existing?->progress_number;
        $total = $data['progress_total'] ?? $existing?->progress_total;

        if ($number !== null && $total !== null && (float) $total > 0) {
            $data['progress_percent'] = (int) round(((float) $number / (float) $total) * 100);
        }

        return $data;
    }

    /**
     * Nhóm F — "Người đã nhận thực hiện": derived field, tự set khi status
     * rời 'not_started' lần đầu tiên (accepted_by còn null). Không có input
     * UI cho field này — set duy nhất ở đây.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyAcceptedTracking(Task $task, array $data): array
    {
        if (! array_key_exists('status', $data)) {
            return $data;
        }

        if ($task->status === 'not_started' && $data['status'] !== 'not_started' && $task->accepted_by === null) {
            $data['accepted_by'] = $task->assignee_id ?? $data['assignee_id'] ?? null;
            $data['accepted_at'] = now();
        }

        return $data;
    }

    /** @return array{error: string}|true */
    public function delete(Task $task): array|bool
    {
        if ($this->tasks->hasChildren($task->id)) {
            return ['error' => 'Xoá các công việc con trước khi xoá mục này.'];
        }

        return $this->tasks->delete($task);
    }

    public function countByProject(int $projectId): int
    {
        return $this->tasks->countByProject($projectId);
    }

    /**
     * Bulk actions (PR7) — chỉ cho phép whitelist manager_id/weight, KHÔNG
     * cho bulk sửa status/title/assignee... tránh sai sót hàng loạt ngoài
     * ý muốn (quyết định đã chốt trong plan). Chỉ áp dụng cho task thuộc
     * phạm vi quyền viewer (project viewer được xem qua forViewer()) — task
     * ngoài phạm vi bị bỏ qua âm thầm, không báo lỗi để tránh rò rỉ thông
     * tin về task người dùng không có quyền truy cập.
     *
     * @param  list<int>  $taskIds
     * @param  array<string, mixed>  $data
     * @return list<Task>
     */
    public function bulkUpdate(array $taskIds, array $data, User $editor): array
    {
        $allowed = array_intersect_key($data, array_flip(['manager_id', 'weight']));
        if ($allowed === [] || $taskIds === []) {
            return [];
        }

        $allowedProjectIds = $this->projects->forViewer(Project::query(), $editor)->pluck('id')->all();
        $allowed['updated_by'] = $editor->id;

        $updated = [];
        foreach ($taskIds as $taskId) {
            $task = $this->tasks->find((int) $taskId);
            if ($task === null || ! in_array($task->project_id, $allowedProjectIds, true)) {
                continue;
            }
            $updated[] = $this->tasks->update($task, $allowed);
        }

        return $updated;
    }

    /**
     * Chuyển giao hàng loạt (Phase 3 §6) — 1 người nhận áp dụng cho toàn bộ
     * task đã chọn. Đổi assignee_id + set origin_department_id (giữ nguyên
     * nếu đã có, else lấy từ project) / delegated_to_department_id (phòng ban
     * người nhận) / delegated_to_employee_id / delegation_status='pending'.
     *
     * @param  list<int>  $taskIds
     * @return list<Task>
     */
    public function bulkDelegate(array $taskIds, int $delegatedToEmployeeId, User $editor): array
    {
        if ($taskIds === []) {
            return [];
        }

        $recipient = $this->projects->findUser($delegatedToEmployeeId);
        if ($recipient === null) {
            return [];
        }

        $allowedProjectIds = $this->projects->forViewer(Project::query(), $editor)->pluck('id')->all();

        $updated = [];
        foreach ($taskIds as $taskId) {
            $task = $this->tasks->find((int) $taskId);
            if ($task === null || ! in_array($task->project_id, $allowedProjectIds, true)) {
                continue;
            }

            $updated[] = $this->tasks->update($task, [
                'assignee_id' => $recipient->id,
                'origin_department_id' => $task->origin_department_id ?? $task->project?->owner_department_id,
                'delegated_to_department_id' => $recipient->department_id,
                'delegated_to_employee_id' => $recipient->id,
                'delegation_status' => 'pending',
                'updated_by' => $editor->id,
            ]);
        }

        if ($updated !== []) {
            $this->notifyDelegation($updated, $recipient, $editor);
        }

        return $updated;
    }

    /** @param  list<Task>  $tasks */
    private function notifyDelegation(array $tasks, User $recipient, User $actor): void
    {
        $count = count($tasks);
        $this->notifications->notify(
            $recipient,
            $actor,
            NotificationService::TYPE_TASK_DELEGATED,
            $count === 1 ? 'Bạn được chuyển giao 1 công việc' : "Bạn được chuyển giao {$count} công việc",
            $tasks[0]->title,
            null,
            ['task_ids' => array_map(fn ($t) => $t->id, $tasks)],
        );
    }

    public function present(Task $task): array
    {
        $overdue = $this->computeOverdue($task);

        return [
            'id' => $task->id,
            'project_id' => $task->project_id,
            'project' => $task->relationLoaded('project') && $task->project !== null
                ? [
                    'id' => $task->project->id,
                    'code' => $task->project->code,
                    'name' => $task->project->name,
                    'owner_department' => $this->presentDept(
                        $task->project->relationLoaded('ownerDepartment')
                            ? $task->project->ownerDepartment
                            : null
                    ),
                    'executing_department' => $this->presentDept(
                        $task->project->relationLoaded('executingDepartment')
                            ? $task->project->executingDepartment
                            : null
                    ),
                ]
                : null,
            'department' => $this->presentDept($this->resolveTaskDepartment($task)),
            'parent_id' => $task->parent_id,
            'parent' => $task->relationLoaded('parent') && $task->parent !== null
                ? ['id' => $task->parent->id, 'code' => $task->parent->code, 'title' => $task->parent->title]
                : null,
            'code' => $task->code,
            'type' => $task->type,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'priority' => $task->priority,
            'start_date' => $task->start_date?->toDateString(),
            'start_time' => $task->start_time,
            'end_date' => $task->end_date?->toDateString(),
            'due_time' => $task->due_time,
            'actual_start_date' => $task->actual_start_date?->toDateString(),
            'actual_end_date' => $task->actual_end_date?->toDateString(),
            'assignee_id' => $task->assignee_id,
            'assignee' => $this->presentUser($task->relationLoaded('assignee') ? $task->assignee : null),
            'progress_percent' => $task->progress_percent,
            'progress_type' => $task->progress_type,
            'progress_number' => $task->progress_number,
            'progress_total' => $task->progress_total,
            'unit' => $task->unit,
            'estimated_hours' => $task->estimated_hours,
            'worklog_hours' => (float) ($task->worklogs_sum_hours ?? 0),
            'manager_id' => $task->manager_id,
            'manager' => $this->presentUser($task->relationLoaded('manager') ? $task->manager : null),
            'accepted_by' => $task->accepted_by,
            'accepted_by_user' => $this->presentUser($task->relationLoaded('acceptedBy') ? $task->acceptedBy : null),
            'accepted_at' => $task->accepted_at?->toIso8601String(),
            'origin_department' => $this->presentDept($task->relationLoaded('originDepartment') ? $task->originDepartment : null),
            'delegated_to_department' => $this->presentDept($task->relationLoaded('delegatedToDepartment') ? $task->delegatedToDepartment : null),
            'delegated_to_employee_id' => $task->delegated_to_employee_id,
            'delegated_to_employee' => $this->presentUser($task->relationLoaded('delegatedToEmployee') ? $task->delegatedToEmployee : null),
            'delegation_status' => $task->delegation_status,
            'weight' => $task->weight,
            'sort_order' => $task->sort_order,
            'attachments_count' => $task->attachments_count ?? 0,
            'is_overdue' => $overdue['is_overdue'],
            'overdue_days' => $overdue['overdue_days'],
            'variance_days' => $overdue['variance_days'],
            'task_score' => $this->presentTaskScore($task->relationLoaded('taskScore') ? $task->taskScore : null),
            'creator' => $this->presentUser($task->relationLoaded('creator') ? $task->creator : null),
            'created_by' => $task->created_by,
            'updated_by' => $task->updated_by,
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Nhóm A — is_overdue/overdue_days/variance_days tính từ 1 nguồn công
     * thức duy nhất, tránh trùng lặp (docs/VA_WORKSPACE_OVERVIEW.md §17):
     *
     * - is_overdue: end_date đã qua hôm nay và trạng thái chưa hoàn thành/huỷ.
     *   PHẢI khớp TaskRepository::scopeOverdue() dùng cho tab/filter "Quá hạn".
     * - variance_days: actual_end_date - end_date (dương = trễ, âm = sớm),
     *   null nếu thiếu 1 trong 2 mốc.
     * - overdue_days: chỉ có ý nghĩa khi task đã completed VÀ variance dương
     *   (hoàn thành trễ) — khác is_overdue (task đang mở, chưa xong, đã qua hạn).
     *
     * @return array{is_overdue: bool, overdue_days: int|null, variance_days: int|null}
     */
    private function computeOverdue(Task $task): array
    {
        $today = now()->startOfDay();
        $isOverdue = $task->end_date !== null
            && $task->end_date->lt($today)
            && ! in_array($task->status, ['completed', 'cancelled'], true);

        $varianceDays = null;
        if ($task->actual_end_date !== null && $task->end_date !== null) {
            $varianceDays = $task->end_date->diffInDays($task->actual_end_date, false);
        }

        $overdueDays = ($task->status === 'completed' && $varianceDays !== null && $varianceDays > 0)
            ? $varianceDays
            : null;

        return ['is_overdue' => $isOverdue, 'overdue_days' => $overdueDays, 'variance_days' => $varianceDays];
    }

    private function presentTaskScore(?TaskScore $score): ?array
    {
        if ($score === null) {
            return null;
        }

        return [
            'rating_score' => $score->rating_score,
            'rating_result' => $score->rating_result,
            'rating_desc' => $score->rating_desc,
            'scored_by' => $score->scored_by,
            'scored_at' => $score->scored_at?->toIso8601String(),
        ];
    }

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

    /**
     * Phòng ban gắn với công việc — ưu tiên phòng nhận chuyển giao, rồi
     * phòng gốc, phòng của người thực hiện, cuối cùng phòng thực hiện /
     * sở hữu của dự án.
     */
    private function resolveTaskDepartment(Task $task): ?Department
    {
        if ($task->relationLoaded('delegatedToDepartment') && $task->delegatedToDepartment) {
            return $task->delegatedToDepartment;
        }

        if ($task->relationLoaded('originDepartment') && $task->originDepartment) {
            return $task->originDepartment;
        }

        $assignee = $task->relationLoaded('assignee') ? $task->assignee : null;
        if ($assignee && $assignee->relationLoaded('department') && $assignee->department) {
            return $assignee->department;
        }

        $project = $task->relationLoaded('project') ? $task->project : null;
        if ($project?->relationLoaded('executingDepartment') && $project->executingDepartment) {
            return $project->executingDepartment;
        }
        if ($project?->relationLoaded('ownerDepartment') && $project->ownerDepartment) {
            return $project->ownerDepartment;
        }

        return null;
    }

    /** @return array{id: int, name: string}|null */
    private function presentDept(?Department $dept): ?array
    {
        if ($dept === null) {
            return null;
        }

        return [
            'id' => $dept->id,
            'name' => $dept->name,
        ];
    }
}
