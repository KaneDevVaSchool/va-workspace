<?php

namespace Modules\Project\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Identity\App\Models\Department;

/**
 * Công việc (Task module — Project Giai đoạn 2: WBS đa cấp, thay thế dần
 * ProjectQuickItem cho kind task/task_category/phase). Task luôn thuộc 1
 * Project qua project_id bắt buộc, tự làm "phase"/"danh mục" qua cột `type`
 * + `parent_id` (không tách bảng phases riêng — xem
 * docs/VA_WORKSPACE_OVERVIEW.md §16, plan Project Giai đoạn 2 QD1/QD2).
 *
 * @property int         $id
 * @property int         $project_id
 * @property int|null    $parent_id
 * @property string|null $code
 * @property string      $type               task | phase | category
 * @property string      $title
 * @property string|null $description
 * @property string      $status
 * @property string|null $priority
 * @property string|null $start_date
 * @property string|null $end_date
 * @property string|null $actual_start_date
 * @property string|null $actual_end_date
 * @property int|null    $assignee_id
 * @property int|null    $progress_percent   0-100, nhập tay ở giai đoạn này
 * @property int         $sort_order
 * @property int|null    $origin_department_id          chừa chỗ Task Delegation (§6) — chưa dùng logic
 * @property int|null    $delegated_to_department_id    chừa chỗ Task Delegation (§6) — chưa dùng logic
 * @property int|null    $delegated_to_employee_id      chừa chỗ Task Delegation (§6) — chưa dùng logic
 * @property string|null $delegation_status             chừa chỗ Task Delegation (§6) — chưa dùng logic
 * @property int|null    $created_by
 * @property int|null    $updated_by
 */
class Task extends Model
{
    protected $table = 'tasks';

    public const WITH_PRESENT = ['project', 'parent', 'assignee', 'creator', 'updater'];

    public const TYPES = ['task', 'phase', 'category'];

    protected $fillable = [
        'project_id',
        'parent_id',
        'code',
        'type',
        'title',
        'description',
        'status',
        'priority',
        'start_date',
        'end_date',
        'actual_start_date',
        'actual_end_date',
        'assignee_id',
        'progress_percent',
        'sort_order',
        'created_by',
        'updated_by',
        // origin_department_id / delegated_to_department_id /
        // delegated_to_employee_id / delegation_status KHÔNG fillable qua
        // form thường ở giai đoạn này (Task Delegation §6 — chỉ chừa cột,
        // sẽ set trực tiếp khi TaskService::delegate() được cài đặt ở
        // Phase 3).
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'progress_percent' => 'integer',
        'sort_order' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')->orderBy('sort_order');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function originDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'origin_department_id');
    }

    public function delegatedToDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'delegated_to_department_id');
    }

    public function delegatedToEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_to_employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
