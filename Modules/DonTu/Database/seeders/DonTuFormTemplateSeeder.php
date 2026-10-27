<?php

namespace Modules\DonTu\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Mẫu biểu theo QT.05: BM.01 (đơn xin nghỉ việc), BM.02 (biên bản bàn giao).
 *
 * php artisan db:seed --class="Modules\DonTu\Database\Seeders\DonTuFormTemplateSeeder"
 */
class DonTuFormTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedResignationForm();
        $this->seedHandoverForm();
    }

    private function seedResignationForm(): void
    {
        $this->insertTemplate(
            'BM01_HN_QT_05',
            'Đơn xin nghỉ việc',
            'Kí hiệu BM.01/HN.QT.05 · Ngày hiệu lực 01/10/2025 · Lần ban hành 01. Phần ý kiến Trưởng đơn vị, HCNS, TGĐ và theo dõi thủ tục xử lý trên luồng duyệt.',
            [
                'form_symbol' => 'BM.01/HN.QT.05',
                'effective_date' => '2025-10-01',
                'issue_number' => '01',
            ],
            [
                ['key' => 'ho_va_ten', 'label' => 'Họ và tên', 'type' => 'text', 'required' => true],
                ['key' => 'cccd', 'label' => 'Số CCCD', 'type' => 'text', 'required' => true],
                ['key' => 'so_dien_thoai', 'label' => 'Số ĐTDĐ liên lạc', 'type' => 'text', 'required' => true],
                ['key' => 'chuc_danh', 'label' => 'Chức danh', 'type' => 'text', 'required' => true],
                ['key' => 'don_vi_phong_ban', 'label' => 'Đơn vị / Phòng ban', 'type' => 'text', 'required' => true],
                ['key' => 'ngay_vao_chinh_thuc', 'label' => 'Ngày vào chính thức', 'type' => 'date', 'required' => true],
                [
                    'key' => 'loai_hdld',
                    'label' => 'Loại HĐLĐ hiện tại',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        'Hợp đồng lao động xác định thời hạn',
                        'Hợp đồng lao động không xác định thời hạn',
                        'Hợp đồng thử việc',
                        'Khác',
                    ],
                ],
                ['key' => 'ngay_het_han_hdld', 'label' => 'Ngày hết hạn HĐLĐ', 'type' => 'date', 'required' => false],
                ['key' => 'ngay_nghi_viec_tu', 'label' => 'Xin nghỉ việc kể từ ngày', 'type' => 'date', 'required' => true],
                ['key' => 'ly_do', 'label' => 'Lý do', 'type' => 'textarea', 'required' => true],
                [
                    'key' => 'cam_ket',
                    'label' => 'Cam kết của người làm đơn',
                    'type' => 'signature',
                    'required' => true,
                    'commitment_text' => 'Tôi cam kết sẽ thực hiện đầy đủ các thủ tục và nghĩa vụ theo quy định đối với Hệ thống Trường Việt Mỹ trước khi nghỉ việc.',
                ],
            ],
        );
    }

    private function seedHandoverForm(): void
    {
        $this->insertTemplate(
            'BM02_HN_QT_05',
            'Biên bản bàn giao công việc',
            'Kí hiệu BM.02/HN.QT.05 · Ngày hiệu lực 01/10/2025 · Lần ban hành 01. Bàn giao công việc, tài sản, trang thiết bị, công cụ dụng cụ.',
            [
                'form_symbol' => 'BM.02/HN.QT.05',
                'effective_date' => '2025-10-01',
                'issue_number' => '01',
            ],
            [
                ['key' => 'gio_bat_dau', 'label' => 'Giờ bắt đầu bàn giao', 'type' => 'text', 'required' => true],
                ['key' => 'phut_bat_dau', 'label' => 'Phút bắt đầu', 'type' => 'text', 'required' => true],
                ['key' => 'ngay_ban_giao', 'label' => 'Ngày bàn giao', 'type' => 'date', 'required' => true],
                ['key' => 'dia_diem_ban_giao', 'label' => 'Địa điểm', 'type' => 'text', 'required' => true],
                ['key' => 'ben_giao_ho_ten', 'label' => 'Bên giao — Họ và tên', 'type' => 'text', 'required' => true],
                ['key' => 'ben_giao_ma_nv', 'label' => 'Bên giao — Mã NV', 'type' => 'text', 'required' => true],
                ['key' => 'ben_giao_chuc_danh', 'label' => 'Bên giao — Chức danh', 'type' => 'text', 'required' => true],
                ['key' => 'ben_giao_phong_ban', 'label' => 'Bên giao — Phòng ban / Bộ phận', 'type' => 'text', 'required' => true],
                ['key' => 'ben_nhan_pm_ho_ten', 'label' => 'Bên nhận (phần mềm) — Họ và tên', 'type' => 'text', 'required' => true],
                ['key' => 'ben_nhan_pm_ma_nv', 'label' => 'Bên nhận (phần mềm) — Mã NV', 'type' => 'text', 'required' => true],
                ['key' => 'ben_nhan_pm_chuc_danh', 'label' => 'Bên nhận (phần mềm) — Chức danh', 'type' => 'text', 'required' => true],
                ['key' => 'ben_nhan_pm_phong_ban', 'label' => 'Bên nhận (phần mềm) — Phòng ban', 'type' => 'text', 'required' => true],
                ['key' => 'ben_nhan_pc_ho_ten', 'label' => 'Bên nhận (phần cứng) — Họ và tên', 'type' => 'text', 'required' => true],
                ['key' => 'ben_nhan_pc_ma_nv', 'label' => 'Bên nhận (phần cứng) — Mã NV', 'type' => 'text', 'required' => true],
                ['key' => 'ben_nhan_pc_chuc_danh', 'label' => 'Bên nhận (phần cứng) — Chức danh', 'type' => 'text', 'required' => true],
                ['key' => 'ben_nhan_pc_phong_ban', 'label' => 'Bên nhận (phần cứng) — Phòng ban', 'type' => 'text', 'required' => true],
                [
                    'key' => 'danh_sach_cong_viec',
                    'label' => '1. Công việc (mỗi dòng: tên công việc | SL | tình trạng | đường dẫn | ghi chú)',
                    'type' => 'textarea',
                    'required' => true,
                ],
                [
                    'key' => 'danh_sach_tai_san',
                    'label' => '2. Tài sản, trang thiết bị, công cụ, dụng cụ (mỗi dòng: tên | SL | tình trạng | ghi chú)',
                    'type' => 'textarea',
                    'required' => false,
                ],
                ['key' => 'email_khoa', 'label' => '3. Email cần khóa / hủy quyền', 'type' => 'text', 'required' => false],
                [
                    'key' => 'quyen_phan_mem',
                    'label' => '3. Quyền sử dụng chương trình, phần mềm',
                    'type' => 'textarea',
                    'required' => false,
                ],
                [
                    'key' => 'tam_ung_cong_no',
                    'label' => '4. Tạm ứng / công nợ (mỗi dòng: khoản | số tiền | tình trạng | ghi chú)',
                    'type' => 'textarea',
                    'required' => false,
                ],
                ['key' => 'gio_ket_thuc', 'label' => 'Giờ kết thúc bàn giao', 'type' => 'text', 'required' => false],
                ['key' => 'phut_ket_thuc', 'label' => 'Phút kết thúc', 'type' => 'text', 'required' => false],
                ['key' => 'ngay_ket_thuc', 'label' => 'Ngày kết thúc bàn giao', 'type' => 'date', 'required' => false],
                [
                    'key' => 'cam_ket_trach_nhiem',
                    'label' => '5. Cam kết trách nhiệm (bên giao)',
                    'type' => 'signature',
                    'required' => true,
                    'commitment_text' => 'Tôi cam kết chịu trách nhiệm về nghiệp vụ đã thực hiện, hợp tác giải quyết phát sinh và không tiết lộ thông tin của Hệ thống Trường Việt Mỹ cho bên thứ ba khi chưa được đồng ý.',
                ],
            ],
        );
    }

    /**
     * @param  array<string, string>  $branding
     * @param  list<array<string, mixed>>  $fields
     */
    private function insertTemplate(string $code, string $name, string $description, array $branding, array $fields): void
    {
        if (DB::table('don_tu_form_templates')->where('code', $code)->exists()) {
            return;
        }

        $now = now();

        DB::table('don_tu_form_templates')->insert([
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'scope' => 'internal',
            'department_id' => null,
            'status' => 'active',
            'version' => 1,
            'schema_json' => json_encode(['fields' => $fields], JSON_UNESCAPED_UNICODE),
            'branding_json' => json_encode($branding, JSON_UNESCAPED_UNICODE),
            'created_by' => null,
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
