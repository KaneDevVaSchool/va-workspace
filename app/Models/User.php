<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Identity\App\Models\Company;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\App\Models\Team;
use Modules\Identity\App\Models\UserConcurrentPosition;
use Modules\Identity\App\Services\PermissionService;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        // Đăng nhập Google (Modules/Identity) — TẠM THỜI giả lập, xem
        // Modules/Identity/App/Repositories/UserRepository.php.
        'department_id',
        'team_id',
        'google_id',
        'avatar_url',
        'status',
        // Đồng bộ từ VA-HRM, xem Modules/Identity/App/Hrm/Services/HrmEmployeeSyncService.php.
        'hrm_employee_uuid',
        'hrm_user_uuid',
        'employee_code',
        'job_title_name',
        'job_position_level',
        'company_id',
        'manager_employee_uuid',
        'manager_display_name',
        'hrm_terminated_at',
        'hrm_synced_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'hrm_terminated_at' => 'datetime',
        'hrm_synced_at' => 'datetime',
    ];

    /**
     * Phòng ban của user. TẠM THỜI giả lập (Modules/Identity) — sẽ thay
     * bằng dữ liệu từ API HRM.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Team của user (Modules/Identity) — sở hữu lâu dài của Workspace,
     * KHÔNG sync từ HRM (khác department, xem Modules/Identity/App/Models/Team.php).
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** Công ty đồng bộ từ VA-HRM — chỉ hiển thị, không dùng trong permission. */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Chức vụ kiêm nhiệm đồng bộ từ VA-HRM (AssignmentResource.is_primary=false). */
    public function concurrentPositions(): HasMany
    {
        return $this->hasMany(UserConcurrentPosition::class);
    }

    /**
     * Cấp trên trực tiếp — resolve runtime qua manager_employee_uuid (snapshot,
     * không phải FK cứng: người này có thể chưa từng login workspace).
     */
    public function resolveManagerUser(): ?self
    {
        if ($this->manager_employee_uuid === null) {
            return null;
        }

        return static::query()->where('hrm_employee_uuid', $this->manager_employee_uuid)->first();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * RBAC tối giản (Modules/Identity) — 1 user có thể giữ nhiều role,
     * xem docs/VA_WORKSPACE_OVERVIEW.md §4.1 cho danh sách 7 role hệ thống.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $code): bool
    {
        return $this->roles->contains('code', $code);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Kiểm tra quyền granular (global scope) — shorthand cho PermissionService.
     * Dùng: $user->allows('task.delegate')
     */
    public function allows(string $permissionKey): bool
    {
        return app(PermissionService::class)->allows($this, $permissionKey);
    }

    /**
     * Kiểm tra quyền granular với scope cụ thể (department hoặc team).
     * Dùng: $user->allowsScoped('project.create', 'department', $deptId)
     */
    public function allowsScoped(string $permissionKey, string $scopeType, ?int $scopeId = null): bool
    {
        return app(PermissionService::class)->allows($this, $permissionKey, $scopeType, $scopeId);
    }
}
