<?php

namespace Modules\Evaluation\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Models\EvaluationCriterionType;
use Modules\Identity\App\Models\Department;

/**
 * Bộ quy tắc đánh giá nhân sự — Phòng Hành chính Nhân sự (NS).
 * Hiệu lực từ 01/10/2025; điểm tháng khởi đầu 100 (áp dụng khi chấm điểm).
 *
 * Idempotent: updateOrCreate theo (department_id, name). Tắt tiêu chí demo cũ của NS.
 */
class HrNsEvaluationCriteriaSeeder extends Seeder
{
    private const DEPARTMENT_CODE = 'NS';

    /** @var list<string> */
    private const DEMO_CRITERION_NAMES = [
        'Chất lượng công việc',
        'Đúng hạn & tiến độ',
        'Mức độ độc lập',
        'Giao tiếp & phối hợp',
        'Tinh thần trách nhiệm',
        'Chuyên cần',
        'Đóng góp tích cực',
        'Tiêu chí thử nghiệm',
    ];

    public function run(): void
    {
        $department = Department::query()->where('code', self::DEPARTMENT_CODE)->first();
        if ($department === null) {
            $this->command?->warn('Không tìm thấy phòng ban code NS — bỏ qua HrNsEvaluationCriteriaSeeder.');

            return;
        }

        $departmentId = (int) $department->id;
        $createdBy = User::query()->orderBy('id')->value('id');

        EvaluationCriteria::query()
            ->where('department_id', $departmentId)
            ->whereIn('name', self::DEMO_CRITERION_NAMES)
            ->update(['is_active' => false]);

        $types = [];
        foreach ($this->types() as $index => $type) {
            $types[$type['code']] = EvaluationCriterionType::query()->updateOrCreate(
                [
                    'department_id' => $departmentId,
                    'code'          => $type['code'],
                ],
                [
                    'name'        => $type['name'],
                    'description' => $type['description'],
                    'sort_order'  => $index,
                    'created_by'  => $createdBy,
                ],
            );
        }

        foreach ($this->criteria() as $index => $criterion) {
            EvaluationCriteria::query()->updateOrCreate(
                [
                    'department_id' => $departmentId,
                    'name'          => $criterion['name'],
                ],
                [
                    'criterion_type_id' => $types[$criterion['type_code']]->id,
                    'type'              => 'behavior',
                    'description'       => $criterion['description'],
                    'levels'            => $criterion['levels'],
                    'is_active'         => true,
                    'allow_half'        => false,
                    'sort_order'        => $index,
                    'created_by'        => $createdBy,
                    'updated_by'        => $createdBy,
                ],
            );
        }

        $this->command?->info(sprintf(
            'Đã seed %d tiêu chí đánh giá cho phòng %s (id=%d).',
            count($this->criteria()),
            $department->name,
            $departmentId,
        ));
    }

    /** @return list<array{code: string, name: string, description: string}> */
    private function types(): array
    {
        return [
            [
                'code'        => 'NS-T01',
                'name'        => 'A. Cơ chế điểm cộng',
                'description' => 'Hành vi vượt trội, tham gia hoạt động, phát triển cá nhân (mã A1–A13).',
            ],
            [
                'code'        => 'NS-T02',
                'name'        => 'B. Kết quả công việc',
                'description' => 'Trễ deadline, sai sót, không đạt KPI/mục tiêu (mã B1–B6).',
            ],
            [
                'code'        => 'NS-T03',
                'name'        => 'C. Thái độ & kỷ luật',
                'description' => 'Thời gian, báo cáo, thái độ, họp, trung thực (mã C1–C6).',
            ],
            [
                'code'        => 'NS-T04',
                'name'        => 'D. Hợp tác & tương tác',
                'description' => 'Hỗ trợ đồng nghiệp, phối hợp liên phòng, quản trị cảm xúc (mã D1–D4).',
            ],
            [
                'code'        => 'NS-T05',
                'name'        => 'E. Năng lực & kỹ năng',
                'description' => 'Lặp lại sai lầm, giải quyết vấn đề (mã E1–E2).',
            ],
        ];
    }

    /**
     * @return list<array{
     *     name: string,
     *     type_code: string,
     *     description: string,
     *     levels: list<array{code: string, label: string, description: string, score: float|int}>
     * }>
     */
    private function criteria(): array
    {
        return [
            // —— A: Bonus ——
            [
                'name'        => 'Chủ động lập kế hoạch & đề xuất giải pháp',
                'type_code'   => 'NS-T01',
                'description' => 'Mã A1. Tự nhận diện vấn đề, xây dựng kế hoạch không cần chờ yêu cầu.',
                'levels'      => [
                    ['code' => 'A1', 'label' => 'A1 — Lập kế hoạch & giải pháp', 'description' => '+6 điểm/lần', 'score' => 6],
                ],
            ],
            [
                'name'        => 'Chủ động nhận công việc báo cáo chung',
                'type_code'   => 'NS-T01',
                'description' => 'Mã A2. Đăng ký nhận thêm báo cáo/công việc chung; tiên phong cải tiến mới.',
                'levels'      => [
                    ['code' => 'A2', 'label' => 'A2 — Nhận báo cáo/công việc chung', 'description' => '+3 điểm/lần', 'score' => 3],
                ],
            ],
            [
                'name'        => 'Sáng kiến & cải tiến',
                'type_code'   => 'NS-T01',
                'description' => 'Mã A3. Ý tưởng/cải tiến nhỏ được áp dụng và mang lại hiệu quả.',
                'levels'      => [
                    ['code' => 'A3', 'label' => 'A3 — Sáng kiến được áp dụng', 'description' => '+5 điểm/lần', 'score' => 5],
                ],
            ],
            [
                'name'        => 'Lan tỏa tích cực',
                'type_code'   => 'NS-T01',
                'description' => 'Mã A4. Giữ tinh thần lạc quan, bình tĩnh dưới áp lực cao.',
                'levels'      => [
                    ['code' => 'A4', 'label' => 'A4 — Lan tỏa tích cực', 'description' => '+5 điểm/lần', 'score' => 5],
                ],
            ],
            [
                'name'        => 'Hỗ trợ đồng nghiệp (khác team)',
                'type_code'   => 'NS-T01',
                'description' => 'Mã A5. Người được hỗ trợ ghi nhận điểm trừ tương ứng. Tối đa 9 điểm cộng/tháng.',
                'levels'      => [
                    ['code' => 'A5', 'label' => 'A5 — Hỗ trợ khác team', 'description' => '+3 điểm/lần (tối đa 9/tháng)', 'score' => 3],
                ],
            ],
            [
                'name'        => 'Hỗ trợ đồng nghiệp (cùng team)',
                'type_code'   => 'NS-T01',
                'description' => 'Mã A6. Hoàn thành trước hạn qua đăng ký với quản lý; người được hỗ trợ ghi nhận trừ tương ứng. Tối đa 10 điểm/tháng.',
                'levels'      => [
                    ['code' => 'A6', 'label' => 'A6 — Hỗ trợ cùng team', 'description' => '+2 điểm/lần (tối đa 10/tháng)', 'score' => 2],
                ],
            ],
            [
                'name'        => 'Tham gia hoạt động, chương trình (CLB & giao lưu)',
                'type_code'   => 'NS-T01',
                'description' => 'Mã A7. CLB chạy bộ (Strava ≥5 km) hoặc sự kiện giao lưu/kết nối công ty tổ chức chính thức.',
                'levels'      => [
                    [
                        'code'        => 'A7a',
                        'label'       => 'A7 — CLB chạy bộ (≥5 km Strava)',
                        'description' => '+2 điểm/lần',
                        'score'       => 2,
                    ],
                    [
                        'code'        => 'A7b',
                        'label'       => 'A7 — Giao lưu/kết nối công ty',
                        'description' => '+2 điểm/lần',
                        'score'       => 2,
                    ],
                ],
            ],
            [
                'name'        => 'Tham gia thi đấu hoạt động trường',
                'type_code'   => 'NS-T01',
                'description' => 'Mã A8.',
                'levels'      => [
                    ['code' => 'A8', 'label' => 'A8 — Thi đấu chương trình trường', 'description' => '+2 điểm/lần', 'score' => 2],
                ],
            ],
            [
                'name'        => 'Hội thi & tổ chức sự kiện công ty',
                'type_code'   => 'NS-T01',
                'description' => 'Mã A9. Giải khuyến khích trở lên hoặc tổ chức/quản lý sự kiện ngoài giờ (chỉ tính mức cao hơn nếu vừa tham gia vừa tổ chức).',
                'levels'      => [
                    [
                        'code'        => 'A9a',
                        'label'       => 'A9 — Thành tích hội thi (≥ giải KK)',
                        'description' => '+3 điểm/lần',
                        'score'       => 3,
                    ],
                    [
                        'code'        => 'A9b',
                        'label'       => 'A9 — Tổ chức/quản lý sự kiện',
                        'description' => '+3 điểm/lần',
                        'score'       => 3,
                    ],
                ],
            ],
            [
                'name'        => 'Phát triển cá nhân (khóa đào tạo bên ngoài)',
                'type_code'   => 'NS-T01',
                'description' => 'Mã A10. Tự túc, gửi chứng chỉ; ưu tiên kỹ năng liên quan công việc. Tối đa 15 điểm/tháng.',
                'levels'      => [
                    ['code' => 'A10', 'label' => 'A10 — Khóa đào tạo bên ngoài', 'description' => '+5 điểm/lần (tối đa 15/tháng)', 'score' => 5],
                ],
            ],
            [
                'name'        => 'Duy trì thành tích cá nhân (3 tháng)',
                'type_code'   => 'NS-T01',
                'description' => 'Mã A11. Ba tháng liên tục tổng điểm tháng ≥100; cộng vào tháng tiếp theo.',
                'levels'      => [
                    ['code' => 'A11', 'label' => 'A11 — 3 tháng ≥100 điểm', 'description' => '+8 điểm (một lần/chu kỳ)', 'score' => 8],
                ],
            ],
            [
                'name'        => 'Tổ chức chuyên đề đào tạo, chia sẻ đội nhóm',
                'type_code'   => 'NS-T01',
                'description' => 'Mã A12. Đề xuất và chia sẻ chuyên đề phục vụ công việc phòng.',
                'levels'      => [
                    ['code' => 'A12', 'label' => 'A12 — Chuyên đề chia sẻ', 'description' => '+6 điểm/lần', 'score' => 6],
                ],
            ],
            [
                'name'        => 'Tham gia đào tạo phòng (Thứ 7)',
                'type_code'   => 'NS-T01',
                'description' => 'Mã A13. Từ 3 buổi trở lên/tháng (tính lại đầu tháng).',
                'levels'      => [
                    ['code' => 'A13', 'label' => 'A13 — Đào tạo T7 (≥3 buổi/tháng)', 'description' => '+2 điểm/lần', 'score' => 2],
                ],
            ],

            // —— B: Work results ——
            [
                'name'        => 'Trễ deadline',
                'type_code'   => 'NS-T02',
                'description' => 'Mã B1–B2.',
                'levels'      => [
                    [
                        'code'        => 'B1',
                        'label'       => 'B1 — Trễ ≤1 ngày làm việc, không ảnh hưởng người khác',
                        'description' => '−2 điểm/lần',
                        'score'       => -2,
                    ],
                    [
                        'code'        => 'B2',
                        'label'       => 'B2 — Trễ >1 ngày hoặc ảnh hưởng công việc người khác',
                        'description' => '−4 điểm/lần',
                        'score'       => -4,
                    ],
                ],
            ],
            [
                'name'        => 'Sai sót trong công việc',
                'type_code'   => 'NS-T02',
                'description' => 'Mã B3–B4.',
                'levels'      => [
                    [
                        'code'        => 'B3',
                        'label'       => 'B3 — Sai sót nhỏ, tự sửa, không hậu quả',
                        'description' => '−2 điểm/lần',
                        'score'       => -2,
                    ],
                    [
                        'code'        => 'B4',
                        'label'       => 'B4 — Nghiêm trọng (số liệu, chi phí, uy tín…)',
                        'description' => '−10 điểm/lần',
                        'score'       => -10,
                    ],
                ],
            ],
            [
                'name'        => 'Không đạt KPI / mục tiêu khối lượng',
                'type_code'   => 'NS-T02',
                'description' => 'Mã B5–B6.',
                'levels'      => [
                    [
                        'code'        => 'B5',
                        'label'       => 'B5 — Hoàn thành 85%–dưới 100%',
                        'description' => '−3 điểm/lần',
                        'score'       => -3,
                    ],
                    [
                        'code'        => 'B6',
                        'label'       => 'B6 — Hoàn thành dưới 85%',
                        'description' => '−5 điểm/lần',
                        'score'       => -5,
                    ],
                ],
            ],

            // —— C: Attitude ——
            [
                'name'        => 'Tuân thủ thời gian & chấm công',
                'type_code'   => 'NS-T03',
                'description' => 'Mã C1. Đi trễ/về sớm không lý do; thiếu dữ liệu chấm công (camera Lễ tân từ 13/10/2025).',
                'levels'      => [
                    ['code' => 'C1', 'label' => 'C1 — Vi phạm thời gian/chấm công', 'description' => '−2 điểm/lần', 'score' => -2],
                ],
            ],
            [
                'name'        => 'Báo cáo & quy trình',
                'type_code'   => 'NS-T03',
                'description' => 'Mã C2. Không tuân thủ quy trình báo cáo (quên gửi, sai biểu mẫu…).',
                'levels'      => [
                    ['code' => 'C2', 'label' => 'C2 — Báo cáo/quy trình', 'description' => '−3 điểm/lần', 'score' => -3],
                ],
            ],
            [
                'name'        => 'Thái độ làm việc (bị động, đẩy việc)',
                'type_code'   => 'NS-T03',
                'description' => 'Mã C3. Bị động, phải thúc giục; không chủ động báo cáo; bàn lùi/đẩy việc.',
                'levels'      => [
                    ['code' => 'C3', 'label' => 'C3 — Thái độ bị động', 'description' => '−5 điểm/lần', 'score' => -5],
                ],
            ],
            [
                'name'        => 'Đổ lỗi, không nhận trách nhiệm',
                'type_code'   => 'NS-T03',
                'description' => 'Mã C4.',
                'levels'      => [
                    ['code' => 'C4', 'label' => 'C4 — Đổ lỗi', 'description' => '−7 điểm/lần', 'score' => -7],
                ],
            ],
            [
                'name'        => 'Vắng mặt họp đã thông báo',
                'type_code'   => 'NS-T03',
                'description' => 'Mã C5.',
                'levels'      => [
                    ['code' => 'C5', 'label' => 'C5 — Vắng họp', 'description' => '−7 điểm/lần', 'score' => -7],
                ],
            ],
            [
                'name'        => 'Không trung thực / vượt thẩm quyền chi phí',
                'type_code'   => 'NS-T03',
                'description' => 'Mã C6. Giấu giếm sai phạm hoặc tự quyết chi phí ngoài thẩm quyền.',
                'levels'      => [
                    ['code' => 'C6', 'label' => 'C6 — Trung thực & thẩm quyền', 'description' => '−10 điểm/lần', 'score' => -10],
                ],
            ],

            // —— D: Collaboration ——
            [
                'name'        => 'Hỗ trợ đồng nghiệp (từ chối / không hợp tác)',
                'type_code'   => 'NS-T04',
                'description' => 'Mã D1.',
                'levels'      => [
                    ['code' => 'D1', 'label' => 'D1 — Từ chối/không hợp tác', 'description' => '−4 điểm/lần', 'score' => -4],
                ],
            ],
            [
                'name'        => 'Phối hợp liên phòng ban',
                'type_code'   => 'NS-T04',
                'description' => 'Mã D2. Phản hồi chậm, ảnh hưởng tiến độ chung.',
                'levels'      => [
                    ['code' => 'D2', 'label' => 'D2 — Phản hồi chậm liên phòng', 'description' => '−5 điểm/lần', 'score' => -5],
                ],
            ],
            [
                'name'        => 'Quản trị cảm xúc',
                'type_code'   => 'NS-T04',
                'description' => 'Mã D3–D4.',
                'levels'      => [
                    [
                        'code'        => 'D3',
                        'label'       => 'D3 — Phản ứng tiêu cực khi nhận góp ý',
                        'description' => '−5 điểm/lần',
                        'score'       => -5,
                    ],
                    [
                        'code'        => 'D4',
                        'label'       => 'D4 — Cảm xúc ảnh hưởng tiêu cực không khí làm việc',
                        'description' => '−7 điểm/lần',
                        'score'       => -7,
                    ],
                ],
            ],

            // —— E: Competence ——
            [
                'name'        => 'Lặp lại sai lầm đã được hướng dẫn',
                'type_code'   => 'NS-T05',
                'description' => 'Mã E1.',
                'levels'      => [
                    ['code' => 'E1', 'label' => 'E1 — Lặp lại lỗi cũ', 'description' => '−5 điểm/lần', 'score' => -5],
                ],
            ],
            [
                'name'        => 'Giải quyết vấn đề cơ bản',
                'type_code'   => 'NS-T05',
                'description' => 'Mã E2. Lúng túng, không đề xuất được phương án xử lý.',
                'levels'      => [
                    ['code' => 'E2', 'label' => 'E2 — Không xử lý được vấn đề cơ bản', 'description' => '−6 điểm/lần', 'score' => -6],
                ],
            ],
        ];
    }
}
