<?php

namespace Modules\Credential\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Credential\App\Enums\CredentialEnums;

/**
 * Tài khoản dịch vụ (Canva, Cursor, Claude, AWS, VPS, database, IAM,
 * domain, Google công ty...). `password` mã hoá 2 chiều — Laravel tự
 * encrypt/decrypt qua cast 'encrypted', không bao giờ lộ plaintext trong DB.
 *
 * @property int $id
 * @property string $name
 * @property int|null $provider_id
 * @property string $account_type admin|superadmin|user|demo|testing
 * @property string|null $username
 * @property string|null $email
 * @property string|null $password mã hoá 2 chiều (cast 'encrypted')
 * @property bool $is_google_login
 * @property string|null $google_account_owner
 * @property string|null $server_name
 * @property string|null $vps_cluster
 * @property string|null $domain
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
        'name',
        'provider_id',
        'account_type',
        'username',
        'email',
        'password',
        'is_google_login',
        'google_account_owner',
        'server_name',
        'vps_cluster',
        'domain',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
