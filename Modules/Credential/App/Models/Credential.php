<?php

namespace Modules\Credential\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Credential\App\Enums\CredentialEnums;
use Modules\Identity\App\Models\Department;

/**
 * Tài khoản dịch vụ (Canva, Cursor, Claude, AWS, VPS, database, IAM,
 * domain, Google công ty...). `password` mã hoá 2 chiều — Laravel tự
 * encrypt/decrypt qua cast 'encrypted', không bao giờ lộ plaintext trong DB.
 *
 * @property int $id
 * @property int|null $department_id phòng ban sở hữu — quyết định ai mặc định thấy được (xem CredentialRepository)
 * @property string $name
 * @property int|null $provider_id
 * @property string $account_type admin|superadmin|user|demo|testing|phu_huynh|giao_vien|hoc_sinh
 * @property string $group external|internal
 * @property string|null $username
 * @property string|null $email
 * @property string|null $password mã hoá 2 chiều (cast 'encrypted')
 * @property bool $is_google_login
 * @property string|null $google_account_owner ghi chú tự do (dữ liệu cũ) — ưu tiên googleAccountOwner() nếu có gắn
 * @property int|null $google_account_owner_id
 * @property string|null $server_name
 * @property string|null $vps_cluster
 * @property string|null $domain
 * @property string|null $access_url link truy cập tài khoản dịch vụ
 * @property string|null $database_name
 * @property bool $is_root_account
 * @property bool $is_iam_account
 * @property string|null $notes
 * @property string|null $purchased_at
 * @property string|null $expires_at
 * @property float|null $monthly_cost
 * @property bool $cost_hidden
 * @property string $currency
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class Credential extends Model
{
    use SoftDeletes;

    protected $table = 'credentials';

    protected $fillable = [
        'department_id',
        'name',
        'provider_id',
        'account_type',
        'group',
        'username',
        'email',
        'password',
        'is_google_login',
        'google_account_owner',
        'google_account_owner_id',
        'server_name',
        'vps_cluster',
        'domain',
        'access_url',
        'database_name',
        'is_root_account',
        'is_iam_account',
        'notes',
        'purchased_at',
        'expires_at',
        'monthly_cost',
        'cost_hidden',
        'currency',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'is_google_login' => 'boolean',
        'is_root_account' => 'boolean',
        'is_iam_account' => 'boolean',
        'cost_hidden' => 'boolean',
        'purchased_at' => 'date',
        'expires_at' => 'date',
        'monthly_cost' => 'decimal:2',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(CredentialProvider::class, 'provider_id');
    }

    /** Phòng ban sở hữu — mặc định ai KHÔNG thuộc phòng ban này sẽ không thấy credential (trừ viewer được cấp riêng). */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** User thật được gắn "Google này thuộc về ai" — ưu tiên hơn cột text google_account_owner cũ. */
    public function googleAccountOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'google_account_owner_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Danh sách user được cấp quyền xem dữ liệu nhạy cảm của credential này. */
    public function viewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'credential_viewers', 'credential_id', 'user_id')
            ->withPivot('granted_by')
            ->withTimestamps();
    }

    /**
     * Trạng thái hạn dùng, tính từ expires_at — không lưu cột riêng.
     * Không có hạn dùng → luôn "đang sử dụng".
     */
    public function getStatusAttribute(): string
    {
        if ($this->expires_at === null) {
            return CredentialEnums::STATUS_ACTIVE;
        }

        $daysLeft = now()->startOfDay()->diffInDays($this->expires_at->startOfDay(), false);

        if ($daysLeft < 0) {
            return CredentialEnums::STATUS_EXPIRED;
        }

        if ($daysLeft <= CredentialEnums::EXPIRING_SOON_DAYS) {
            return CredentialEnums::STATUS_EXPIRING_SOON;
        }

        if ($daysLeft <= CredentialEnums::RENEWING_SOON_DAYS) {
            return CredentialEnums::STATUS_RENEWING_SOON;
        }

        return CredentialEnums::STATUS_ACTIVE;
    }
}
