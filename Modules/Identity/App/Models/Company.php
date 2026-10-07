<?php

namespace Modules\Identity\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Công ty — đồng bộ từ VA-HRM (bảng tra cứu/hiển thị thuần, không tham gia
 * điều kiện permission nào). Xem Modules/Identity/App/Hrm/Services/HrmEmployeeSyncService.php.
 *
 * @property int $id
 * @property string $hrm_uuid
 * @property string $code
 * @property string $name
 * @property bool $is_active
 */
class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'hrm_uuid',
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Pháp nhân workspace đôi khi còn mã HRM cũ (cùng `code`, khác `hrm_uuid`).
     * Khớp theo uuid, rồi theo code, để không insert trùng unique `code`.
     */
    public static function upsertFromHrm(string $uuid, ?string $code, ?string $name): self
    {
        $code = trim((string) $code);
        if ($code === '') {
            $code = $uuid;
        }
        $name = trim((string) $name);
        if ($name === '') {
            $name = $code;
        }

        $byUuid = self::query()->where('hrm_uuid', $uuid)->first();
        if ($byUuid !== null) {
            $byUuid->name = $name;
            $codeTaken = self::query()->where('code', $code)->where('id', '!=', $byUuid->id)->exists();
            if (! $codeTaken) {
                $byUuid->code = $code;
            }
            $byUuid->save();

            return $byUuid;
        }

        $byCode = self::query()->where('code', $code)->first();
        if ($byCode !== null) {
            $byCode->hrm_uuid = $uuid;
            $byCode->name = $name;
            $byCode->save();

            return $byCode;
        }

        return self::query()->create([
            'hrm_uuid' => $uuid,
            'code' => $code,
            'name' => $name,
        ]);
    }
}
