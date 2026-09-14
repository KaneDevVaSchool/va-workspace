<?php

namespace Modules\Credential\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Credential\App\Enums\CredentialEnums;
use Modules\Credential\App\Models\Credential;
use Modules\Credential\App\Models\CredentialProvider;

/**
 * 11 công cụ nội bộ do VA Schools tự phát triển — toàn bộ đăng nhập qua
 * Google Workspace công ty (is_google_login = true, không có username/
 * password riêng). group = internal. Đếm chính xác từ bảng gốc: SIS, LMS
 * Admin, LMS-Cổng GV/HS, Biểu Phí, HRM, POS, Supply, TLGT, RGL, Điều
 * vận, Cơ hội bất ngờ = 11 dòng có tên; dòng thứ 12 (hoàn toàn trống)
 * trong bảng gốc bị loại bỏ, không seed.
 *
 * `google_account_owner` ghi chú chung "toàn bộ nhân viên qua Google
 * Workspace công ty" — KHÔNG gán google_account_owner_id cho 1 cá nhân
 * cụ thể vì đây là tài khoản OAuth dùng chung theo vai trò, không thuộc
 * về 1 người. Trạng thái vận hành "Đang hoàn thiện" trong bảng gốc
 * (khác với status hạn dùng của hệ thống, vốn tính từ expires_at) được
 * ghi tiền tố "[Đang triển khai] " vào notes thay vì thêm cột DB mới.
 *
 * Idempotent theo `name` (không trùng tên trong nhóm này).
 */
class CredentialInternalToolsSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'khoana@hcm.vaschools.edu.vn')->first();
        $provider = CredentialProvider::query()->where('name', 'VA Schools')->first();
        $googleOwnerNote = 'Toàn bộ nhân viên qua tài khoản Google Workspace công ty (không gán cho 1 cá nhân cụ thể).';

        $rows = [
            [
                'name' => 'SIS - Phần mềm Quản lý học sinh', 'access_url' => 'https://sis.vaschools.edu.vn/',
                'account_type' => 'admin', 'in_progress' => false,
                'notes' => 'Hệ thống quản lý học sinh cho Ban Tiếng Anh: phân công giáo viên, nhập điểm, nhận xét, ghi nhận lịch sử học tập qua từng năm.',
            ],
            [
                'name' => 'LMS - Phần mềm Quản lý học tập', 'access_url' => 'https://lms.vaschools.edu.vn/admin/login',
                'account_type' => 'admin', 'in_progress' => true,
                'notes' => 'Nền tảng học tập trực tuyến kết nối giáo viên, học sinh và nhà trường: quản lý khóa học, tài liệu học tập, bài giảng, bài tập, kiểm tra, đánh giá và báo cáo học tập.',
            ],
            [
                'name' => 'LMS - Cổng Giáo viên/Học sinh', 'access_url' => 'https://lms.vaschools.edu.vn/',
                'account_type' => 'user', 'in_progress' => true,
                'notes' => 'Suy luận từ ngữ cảnh: bảng gốc không ghi tên phần mềm cho dòng này, chỉ có link trùng với LMS Admin — có thể là cổng đăng nhập phụ dành cho Giáo viên/Học sinh của cùng hệ thống LMS. Dùng chung cho Giáo viên và Học sinh.',
            ],
            [
                'name' => 'Biểu Phí - Phần mềm Quản lý Biểu phí', 'access_url' => 'https://bieuphi-stag.vaschools.edu.vn',
                'account_type' => 'admin', 'in_progress' => true,
                'notes' => 'Hệ thống quản lý biểu phí.',
            ],
            [
                'name' => 'HRM - Phần mềm Quản lý Nhân sự', 'access_url' => 'https://hrm.vaschools.edu.vn',
                'account_type' => 'admin', 'in_progress' => true,
                'notes' => 'Hệ thống quản lý nhân sự.',
            ],
            [
                'name' => 'POS - Phần mềm Quản lý bán hàng', 'access_url' => 'https://pos.vaschools.edu.vn',
                'account_type' => 'admin', 'in_progress' => false,
                'notes' => 'Hệ thống bán hàng nội bộ: tư vấn viên tạo đơn, kế toán thu tiền, kho xuất hàng và theo dõi tồn, kèm quản lý sản phẩm và báo cáo.',
            ],
            [
                'name' => 'Supply - Phần mềm Quản lý Kho (Ecom)', 'access_url' => 'https://my.vaschools.edu.vn/',
                'account_type' => 'admin', 'in_progress' => true,
                'notes' => 'Kênh phụ huynh.',
            ],
            [
                'name' => 'TLGT - Phần mềm Thanh toán giá trị', 'access_url' => 'https://thanhlygiatri.vaschools.edu.vn/admin',
                'account_type' => 'admin', 'in_progress' => false,
                'notes' => null,
            ],
            [
                'name' => 'RGL (Rút gọn Link)', 'access_url' => 'https://dk.vaschools.edu.vn',
                'account_type' => 'admin', 'in_progress' => false,
                'notes' => null,
            ],
            [
                'name' => 'Điều vận', 'access_url' => 'https://dieuvan.vaschools.edu.vn/',
                'account_type' => 'admin', 'in_progress' => false,
                'notes' => null,
            ],
            [
                'name' => 'Cơ hội bất ngờ', 'access_url' => 'https://cohoibatngo.vaschools.edu.vn/admin',
                'account_type' => 'admin', 'in_progress' => false,
                'notes' => null,
            ],
        ];

        foreach ($rows as $row) {
            $notes = $row['in_progress'] ? '[Đang triển khai] ' : '';
            $notes .= $row['notes'] ?? '';
            $notes = trim($notes) !== '' ? trim($notes) : null;

            Credential::query()->updateOrCreate(
                ['name' => $row['name']],
                [
                    // Seeder gọi Eloquent trực tiếp — set thủ công theo phòng
                    // ban của $superadmin (xem CredentialSeeder cùng lý do).
                    'department_id' => $superadmin?->department_id,
                    'provider_id' => $provider?->id,
                    'account_type' => $row['account_type'],
                    'group' => CredentialEnums::GROUP_INTERNAL,
                    'access_url' => $row['access_url'],
                    'is_google_login' => true,
                    'google_account_owner' => $googleOwnerNote,
                    'google_account_owner_id' => null,
                    'notes' => $notes,
                    'created_by' => $superadmin?->id,
                    'updated_by' => $superadmin?->id,
                ],
            );
        }
    }
}
