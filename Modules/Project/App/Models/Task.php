<?php

namespace Modules\Project\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property string|null $start_time         giờ trong ngày start_date, tuỳ chọn
 * @property string|null $end_date
 * @property string|null $due_time           giờ hạn trong ngày end_date, tuỳ chọn
 * @property string|null $actual_start_date
 * @property string|null $actual_end_date
 * @property int|null    $assignee_id
 * @property int|null    $progress_percent   0-100 — nhập tay khi progress_type=percent,
 *                                            tự tính khi progress_type=quantity (TaskService)
 * @property string      $progress_type      percent | quantity
 * @property float|null  $progress_number    khối lượng đã hoàn thành (khi quantity)
 * @property float|null  $progress_total     khối lượng cần hoàn thành — mẫu số (khi quantity)
 * @property string|null $unit               đơn vị đo khối lượng, tự do
 * @property float|null  $estimated_hours    thời gian dự kiến, nhập tay
 * @property int         $sort_order
 * @property float|null  $weight             % tỷ trọng trong phạm vi Project, nhập tay
 * @property int|null    $manager_id         người quản lý, nhập tay — không mặc định = creator/assignee
 * @property int|null    $accepted_by        người đã nhận thực hiện — derived, TaskService tự set
 * @property string|null $accepted_at        thời điểm nhận — derived, TaskService tự set
 * @property int|null    $origin_department_id          phòng ban gốc khi chuyển giao (Task Delegation §6)
 * @property int|null    $delegated_to_department_id    phòng ban người tiếp nhận
 * @property int|null    $delegated_to_employee_id      người tiếp nhận
 * @property string|null $delegation_status             pending | accepted | in_progress | done | rejected
 * @property int|null    $created_by
 * @property int|null    $updated_by
 */
class Task extends Model
{
    protected $table = 'tasks';

    public const WITH_PRESENT = [
        'project.ownerDepartment',
        'project.executingDepartment',
        'parent',
        'assignee.department',
        'manager',
        'acceptedBy',
        'creator',
        'updater',
        'taskScore',
        'originDepartment',
        'delegatedToDepartment',
        'delegatedToEmployee',
    ];

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
        'start_time',
        'end_date',
        'due_time',
        'actual_start_date',
        'actual_end_date',
        'assignee_id',
        'progress_percent',
        'progress_type',
        'progress_number',
        'progress_total',
        'unit',
        'estimated_hours',
        'sort_order',
        'weight',
        'manager_id',
        'created_by',
        'updated_by',
        // origin_department_id / delegated_to_department_id /
        // delegated_to_employee_id / delegation_status KHÔNG fillable qua
        // form thường — TaskService::bulkDelegate() set qua Repository
        // forceFill() (Task Delegation §6).
        //
        // accepted_by / accepted_at KHÔNG fillable — derived field, chỉ
        // TaskService::applyAcceptedTracking() set qua forceFill trong
        // Repository, giống pattern origin_department_id ở trên.
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'progress_percent' => 'integer',
        'progress_number' => 'decimal:2',
        'progress_total' => 'decimal:2',
        'estimated_hours' => 'decimal:2',
        'weight' => 'decimal:2',
        'sort_order' => 'integer',
        'accepted_at' => 'datetime',
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

    /** Người quản lý — nhập tay, không mặc định = creator/assignee. */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** Người đã nhận thực hiện — derived, xem TaskService::applyAcceptedTracking(). */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    /** Đánh giá tối thiểu — 1 task = 1 bản ghi hiện hành (unique task_id). */
    public function taskScore(): HasOne
    {
        return $this->hasOne(TaskScore::class);
    }

    /** Worklog chấm công giờ thực tế — mỗi người chỉ tự ghi giờ của mình. */
    public function worklogs(): HasMany
    {
        return $this->hasMany(TaskWorklog::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
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
