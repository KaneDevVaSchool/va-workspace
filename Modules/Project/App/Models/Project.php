<?php

namespace Modules\Project\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Identity\App\Models\Department;

/**
 * Dự án (Project module — giai đoạn 1: CRUD, mở rộng phân quyền/nhãn/theo dõi
 * ở giai đoạn 2).
 *
 * @property int         $id
 * @property string      $code               mã tự sinh, ví dụ PRJ0001
 * @property string      $type
 * @property string      $name
 * @property int|null    $lead_user_id
 * @property int|null    $lead_department_id       phòng ban phụ trách
 * @property int|null    $owner_department_id      phòng ban sở hữu — set 1 lần lúc tạo, không sửa được
 * @property int|null    $executing_department_id  phòng ban thực hiện chính (đồng bộ từ pivot, tương thích cũ)
 * @property string|null $start_date
 * @property string|null $end_date
 * @property string|null $actual_start_date
 * @property string|null $actual_end_date
 * @property string      $progress_method
 * @property string      $status
 * @property string      $importance
 * @property bool        $shift_task_dates_with_project
 * @property bool        $hide_cross_tasks_from_assignees
 * @property bool        $hide_child_tasks_from_followers
 * @property bool        $constrain_task_dates_to_project
 * @property string|null $description
 * @property string|null $avatar_path
 * @property float|null  $evaluation_score   để trống — tổng hợp từ Task tương lai
 * @property int|null    $created_by
 * @property int|null    $updated_by
 */
class Project extends Model
{
    protected $table = 'projects';

    public const WITH_PRESENT = [
        'scopes.department',
        'members.department',
        'followers.department',
        'attachments.uploader',
        'labels',
        'ownerDepartment',
        'executingDepartment',
        'executingDepartments',
        'lead.department',
        'leadDepartment',
        'creator.department',
        'updater.department',
    ];

    protected $fillable = [
        'code',
        'type',
        'name',
        'lead_user_id',
        'lead_department_id',
        'owner_department_id',
        'executing_department_id',
        'start_date',
        'end_date',
        'actual_start_date',
        'actual_end_date',
        'progress_method',
        'status',
        'importance',
        'description',
        'shift_task_dates_with_project',
        'hide_cross_tasks_from_assignees',
        'hide_child_tasks_from_followers',
        'constrain_task_dates_to_project',
        'avatar_path',
        'evaluation_score',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'evaluation_score' => 'decimal:2',
        'shift_task_dates_with_project' => 'boolean',
        'hide_cross_tasks_from_assignees' => 'boolean',
        'hide_child_tasks_from_followers' => 'boolean',
        'constrain_task_dates_to_project' => 'boolean',
    ];

    public function scopes(): HasMany
    {
        return $this->hasMany(ProjectScope::class);
    }

    /** N-N với users qua project_members — chỉ liên kết thuần, không cột phụ. */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members', 'project_id', 'user_id')
            ->withTimestamps();
    }

    /** N-N với users qua project_followers — user tự theo dõi dự án (mục B). */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_followers', 'project_id', 'user_id')
            ->withTimestamps();
    }

    /** N-N với nhãn tự do qua project_label_assignments (mục E). */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(ProjectLabel::class, 'project_label_assignments', 'project_id', 'project_label_id')
            ->withTimestamps();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class);
    }

    public function quickItems(): HasMany
    {
        return $this->hasMany(ProjectQuickItem::class);
    }

    /** Công việc thật (Project Giai đoạn 2) — thay thế dần quickItems(). */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_user_id');
    }

    public function leadDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'lead_department_id');
    }

    public function ownerDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'owner_department_id');
    }

    public function executingDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'executing_department_id');
    }

    /** N-N phòng ban được giao thực hiện (được chọn nhiều). */
    public function executingDepartments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'project_executing_departments', 'project_id', 'department_id')
            ->withTimestamps();
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
