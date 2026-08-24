<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;

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
        'google_id',
        'avatar_url',
        'status',
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
    ];

    /**
     * Phòng ban của user. TẠM THỜI giả lập (Modules/Identity) — sẽ thay
     * bằng dữ liệu từ API HRM.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
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
}
