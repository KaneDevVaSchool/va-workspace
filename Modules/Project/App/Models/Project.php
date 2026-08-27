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
 * @property int|null    $owner_department_id      phòng ban sở hữu — set 1 lần lúc tạo, không sửa được
 * @property int|null    $executing_department_id  phòng ban được giao thực hiện (nullable)
 * @property string|null $start_date
 * @property string|null $end_date
 * @property string      $progress_method
 * @property string      $status
 * @property string      $importance
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
        'members',
        'followers',
        'attachments.uploader',
        'labels',
        'ownerDepartment',
        'executingDepartment',
        'lead',
        'creator',
        'updater',
    ];

    protected $fillable = [
        'code',
        'type',
        'name',
        'lead_user_id',
        'owner_department_id',
        'executing_department_id',
        'start_date',
        'end_date',
        'progress_method',
        'status',
        'importance',
        'description',
        'avatar_path',
        'evaluation_score',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'evaluation_score' => 'decimal:2',
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

    public function lead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_user_id');
    }

    public function ownerDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'owner_department_id');
    }

    public function executingDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'executing_department_id');
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
