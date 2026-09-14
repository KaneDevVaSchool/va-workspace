<?php

namespace Modules\Credential\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Credential\App\Enums\CredentialEnums;
use Modules\Credential\App\Models\Credential;
use Modules\Credential\App\Models\CredentialProvider;

/**
 * 11 tài khoản phần mềm/dịch vụ bên ngoài công ty (VNPT, Kids Online,
 * Viettel, Lingua Attack, Raz Plus, Kids A-Z) — dữ liệu thật do người
 * dùng cung cấp, MẬT KHẨU THẬT (đã xác nhận đồng ý seed nguyên văn).
 * group = external (mặc định). Không set purchased_at/expires_at/
 * monthly_cost — bảng gốc không có dữ liệu chi phí, nên các bản ghi này
 * KHÔNG xuất hiện trong tab Dự toán chi phí (allWithCost() chỉ lấy bản
 * ghi có monthly_cost > 0).
 *
 * Idempotent theo cặp (name, username) — 3 dòng "K12 Online" (Viettel)
 * trùng tên nhưng khác username nên đặt tên phân biệt theo trường học.
 */
class CredentialExternalAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'khoana@hcm.vaschools.edu.vn')->first();
        $iconPasswordNote = 'Mật khẩu hiển thị dạng icon hình ảnh (trái dâu) trên giao diện đăng nhập, không phải chuỗi ký tự — xem trực tiếp trên thiết bị đã đăng nhập sẵn.';

        $rows = [
            [
                'provider' => 'VNPT', 'name' => 'Phần mềm vnEdu Connect',
                'access_url' => 'https://vnedu.vn/', 'account_type' => 'admin',
                'username' => 'vm.ntkngoc', 'password' => 'Ngoc@2026',
                'notes' => 'Quản trị 5 trường.',
            ],
            [
                'provider' => 'VNPT', 'name' => 'App Mobile Phụ huynh',
                'access_url' => 'https://apps.apple.com/vn/app/vnedu-connect/id1448464007?l=vi',
                'account_type' => 'phu_huynh',
                'username' => '0782897112', 'password' => '826435',
                'notes' => 'Phụ huynh 3 học sinh từ 3 cấp 1, 2, 3.',
            ],
            [
                'provider' => 'VNPT', 'name' => 'App Mobile Giáo viên',
                'access_url' => 'https://apps.apple.com/vn/app/vnedu-teacher/id916676535?l=vi',
                'account_type' => 'giao_vien',
                'username' => 'vm.ntkngoc', 'password' => 'Ngoc@2026',
                'notes' => null,
            ],
            [
                'provider' => 'Kids Online', 'name' => 'Phần mềm KidsOnline',
                'access_url' => 'https://komt.kidsonline.edu.vn/v4/login', 'account_type' => 'admin',
                'username' => '0782897112', 'password' => 'Va@kidsonline',
                'notes' => 'Quản trị 4 trường mầm non (HCM).',
            ],
            [
                'provider' => 'Viettel', 'name' => 'K12 Online - THPT Việt Mỹ Anh',
                'access_url' => 'https://k12online.vn/', 'account_type' => 'admin',
                'username' => '790007a2', 'password' => 'Viettel@2026',
                'notes' => 'Trường THPT Việt Mỹ Anh.',
            ],
            [
                'provider' => 'Viettel', 'name' => 'K12 Online - TH-THCS-THPT Việt Mỹ',
                'access_url' => 'https://k12online.vn/', 'account_type' => 'admin',
                'username' => '77000830', 'password' => 'Viettel@2026',
                'notes' => 'Trường TH-THCS-THPT Việt Mỹ.',
            ],
            [
                'provider' => 'Viettel', 'name' => 'K12 Online - THCS Việt Mỹ',
                'access_url' => 'https://k12online.vn/', 'account_type' => 'admin',
                'username' => '79772511', 'password' => 'Viettel@2026',
                'notes' => 'Trường THCS Việt Mỹ.',
            ],
            [
                'provider' => 'Lingua Attack', 'name' => 'Lingua Attack',
                'access_url' => 'https://lingua-attack.com/en-EA/user/login', 'account_type' => 'giao_vien',
                'username' => 'vyntt@vaschools.edu.vn', 'password' => 'VAteacher',
                'notes' => 'Tài khoản giáo viên.',
            ],
            [
                'provider' => 'Raz Plus', 'name' => 'Raz Plus',
                'access_url' => 'https://www.raz-plus.com/', 'account_type' => 'giao_vien',
                'username' => 'ved00', 'password' => 'va2627call',
                'notes' => 'Tài khoản giáo viên.',
            ],
            [
                'provider' => 'Kids A-Z', 'name' => 'Kids A-Z (Giáo viên)',
                'access_url' => 'https://www.kidsa-z.com/ng/', 'account_type' => 'giao_vien',
                'username' => 'ved00', 'password' => 'Icon trái dâu',
                'notes' => 'Tài khoản giáo viên. '.$iconPasswordNote,
            ],
            [
                'provider' => 'Kids A-Z', 'name' => 'Kids A-Z (Học sinh - Jasmin)',
                'access_url' => 'https://www.kidsa-z.com/ng/', 'account_type' => 'hoc_sinh',
                'username' => 'Jasmin', 'password' => 'Icon trái dâu',
                'notes' => 'Tài khoản học sinh. '.$iconPasswordNote,
            ],
        ];

        foreach ($rows as $row) {
            $provider = CredentialProvider::query()->where('name', $row['provider'])->first();

            Credential::query()->updateOrCreate(
                ['name' => $row['name'], 'username' => $row['username']],
                [
                    // Seeder gọi Eloquent trực tiếp — set thủ công theo phòng
                    // ban của $superadmin (xem CredentialSeeder cùng lý do).
                    'department_id' => $superadmin?->department_id,
                    'provider_id' => $provider?->id,
                    'account_type' => $row['account_type'],
                    'group' => CredentialEnums::GROUP_EXTERNAL,
                    'access_url' => $row['access_url'],
                    'username' => $row['username'],
                    'password' => $row['password'],
                    'notes' => $row['notes'],
                    'created_by' => $superadmin?->id,
                    'updated_by' => $superadmin?->id,
                ],
            );
        }
    }
}
