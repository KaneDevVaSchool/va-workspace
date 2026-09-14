<?php

namespace Modules\Credential\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Modules\Credential\App\Models\Credential;
use Modules\Credential\App\Models\CredentialProvider;

/**
 * Tài khoản mẫu để có dữ liệu khởi động cho tab Dự toán chi phí. Chi phí
 * và ngày mua lấy từ 2 hoá đơn Anthropic thật ($22.22/tháng/hoá đơn gồm
 * VAT, gói Claude Pro, ngày phát hành 11/09/2026), quy đổi VND theo
 * CredentialEnums::USD_TO_VND — KHÔNG lưu tên/email cá nhân của người trả
 * hoá đơn ngoài hệ thống, chỉ gắn creator/viewer với user quản trị đã có
 * sẵn trong hệ thống (nếu tìm thấy). expires_at KHÔNG set trực tiếp ở đây
 * — để CredentialService::create() tự tính = purchased_at + 1 tháng khi
 * seed chạy qua Service (Repository::create() ở dưới đây gọi Eloquent
 * trực tiếp, KHÔNG qua Service, nên set purchased_at ở seed, ai chạy
 * artisan tinker/factory tạo bản ghi mới qua Service mới có auto-tính).
 * Idempotent theo `name` — 2 hoá đơn ứng với 2 tài khoản Claude Pro riêng
 * biệt:
 * - "Claude Pro" — mua qua email phongcongnghe@... (hộp thư CHUNG của
 *   Phòng Công nghệ, không phải cá nhân).
 * - "Claude Pro - Tổ chức" — mua qua email superadmin khoana@hcm... (tài
 *   khoản cá nhân Nguyễn Anh Khoa, phòng CNTT — xem
 *   CnttSoftwareTeamSeeder), đứng tên "...'s Organization" trên hoá đơn.
 */
class CredentialSeeder extends Seeder
{
    public function run(): void
    {
        $provider = CredentialProvider::query()->where('name', 'Claude')->first();
        $purchasedAt = '2026-09-11';

        $this->seedOne(
            name: 'Claude Pro',
            notes: 'Gói Claude Pro dùng cho công việc lập trình/AI, mua qua hộp thư chung của Phòng Công nghệ.',
            ownerEmail: 'phongcongnghe@vaschools.edu.vn',
            providerId: $provider?->id,
            purchasedAt: $purchasedAt,
        );

        $this->seedOne(
            name: 'Claude Pro - Tổ chức',
            notes: 'Gói Claude Pro đứng tên tổ chức, mua qua tài khoản quản trị cấp cao của Nguyễn Anh Khoa.',
            ownerEmail: 'khoana@hcm.vaschools.edu.vn',
            providerId: $provider?->id,
            purchasedAt: $purchasedAt,
        );
    }

    private function seedOne(string $name, string $notes, string $ownerEmail, ?int $providerId, string $purchasedAt): void
    {
        $owner = User::query()->where('email', $ownerEmail)->first();
        $expiresAt = Carbon::parse($purchasedAt)->addMonthNoOverflow(1)->toDateString();

        $credential = Credential::query()->updateOrCreate(
            ['name' => $name],
            [
                // Seeder gọi Eloquent trực tiếp (không qua CredentialService::create()),
                // nên department_id không tự gán theo creator — set thủ công theo
                // phòng ban của $owner để credential có phòng ban sở hữu ngay từ
                // đầu, không rơi vào tình trạng "chưa gán" (chỉ superadmin thấy).
                'department_id' => $owner?->department_id,
                'provider_id' => $providerId,
                'account_type' => 'admin',
                'notes' => $notes,
                'purchased_at' => $purchasedAt,
                'expires_at' => $expiresAt,
                'monthly_cost' => 578000,
                'currency' => 'VND',
                'created_by' => $owner?->id,
                'updated_by' => $owner?->id,
            ],
        );

        if ($owner !== null) {
            $credential->viewers()->syncWithoutDetaching([
                $owner->id => ['granted_by' => $owner->id],
            ]);
        }
    }
}
