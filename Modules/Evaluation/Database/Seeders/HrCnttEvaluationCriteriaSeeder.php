<?php

namespace Modules\Evaluation\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Models\EvaluationCriterionType;
use Modules\Identity\App\Models\Department;

/**
 * Bộ tiêu chí đánh giá — Phòng Công nghệ thông tin (CNTT).
 * Áp dụng khi một người có nhiều nhiệm vụ khác nhau: chấm theo từng công việc,
 * quy đổi qua trọng số quan trọng (B1) rồi trừ theo sai sót (B3/B4), có giảm trừ
 * theo tốc độ khắc phục (B5); song song là các hệ số hành vi/thái độ (A1, A3–A5)
 * và điểm thưởng đột xuất (A2).
 *
 * Idempotent: updateOrCreate theo (department_id, name). Tắt tiêu chí demo cũ của CNTT.
 */
class HrCnttEvaluationCriteriaSeeder extends Seeder
{
    private const DEPARTMENT_CODE = 'CNTT';

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
            $this->command?->warn('Không tìm thấy phòng ban code CNTT — bỏ qua HrCnttEvaluationCriteriaSeeder.');

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
                    'type'              => $criterion['type'],
                    'description'       => $criterion['description'],
                    'levels'            => $criterion['levels'],
                    'is_active'         => true,
                    'allow_half'        => $criterion['allow_half'],
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
                'code'        => 'CNTT-T01',
                'name'        => 'B. Trọng số & mức hoàn thành công việc',
                'description' => 'Quy đổi mức độ quan trọng và mức hoàn thành theo từng nhiệm vụ (mã B1–B2).',
            ],
            [
                'code'        => 'CNTT-T02',
                'name'        => 'B. Sai sót & khắc phục hậu quả',
                'description' => 'Trừ điểm sai sót chủ quan/khách quan, giảm trừ theo tốc độ khắc phục (mã B3–B5).',
            ],
            [
                'code'        => 'CNTT-T03',
                'name'        => 'A. Thái độ & tinh thần trách nhiệm',
                'description' => 'Tính cam kết, kỷ luật, chịu trách nhiệm đến cùng (mã A1).',
            ],
            [
                'code'        => 'CNTT-T04',
                'name'        => 'A. Điểm thưởng đột xuất',
                'description' => 'Thành tích đặc biệt phát sinh ngoài công việc thường xuyên (mã A2).',
            ],
            [
                'code'        => 'CNTT-T05',
                'name'        => 'A. Chủ động & sáng tạo',
                'description' => 'Khả năng cải tiến, đề xuất giải pháp mới (mã A3).',
            ],
            [
                'code'        => 'CNTT-T06',
                'name'        => 'A. Teamwork',
                'description' => 'Khả năng phối hợp và hỗ trợ đồng đội — đánh giá top-down và 360 độ (mã A4–A5).',
            ],
        ];
    }

    /**
     * @return list<array{
     *     name: string,
     *     type_code: string,
     *     type: string,
     *     description: string,
     *     allow_half: bool,
     *     levels: list<array{code: string, label: string, description: string, score: float|int}>
     * }>
     */
    private function criteria(): array
    {
        return [
            // —— B1: Trọng số mức độ quan trọng của công việc ——
            [
                'name'        => 'Mức độ quan trọng của công việc',
                'type_code'   => 'CNTT-T01',
                'type'        => 'scale',
                'description' => 'Mã B1. Xác định trọng số quan trọng của nhiệm vụ trước khi chấm mức hoàn thành.',
                'allow_half'  => false,
                'levels'      => [
                    ['code' => 'B1-1', 'label' => 'Phụ trợ',            'description' => 'Tác động nhỏ. Trọng số x1.',                                                        'score' => 1],
                    ['code' => 'B1-2', 'label' => 'Hỗ trợ',             'description' => 'Tác động gián tiếp. Trọng số x2.',                                                   'score' => 2],
                    ['code' => 'B1-3', 'label' => 'Quan trọng',         'description' => 'Ảnh hưởng đến vận hành thường xuyên, trực tiếp. Trọng số x3.',                     'score' => 3],
                    ['code' => 'B1-4', 'label' => 'Ưu tiên cao',        'description' => 'Ảnh hưởng đến kết quả phòng ban chức năng, hoặc khách hàng nội bộ/bên ngoài. Trọng số x4.', 'score' => 4],
                    ['code' => 'B1-5', 'label' => 'Chiến lược / Sống còn', 'description' => 'Ảnh hưởng trực tiếp đến thương hiệu, pháp lý, định hướng dài hạn, doanh thu, uy tín công ty. Trọng số x5.', 'score' => 5],
                ],
            ],

            // —— B2: Mức độ hoàn thành công việc ——
            [
                'name'        => 'Mức độ hoàn thành công việc',
                'type_code'   => 'CNTT-T01',
                'type'        => 'scale',
                'description' => 'Mã B2. Áp dụng như hệ số khấu trừ cuối cùng, tham chiếu theo % chỉ tiêu đạt được.',
                'allow_half'  => false,
                'levels'      => [
                    ['code' => 'B2-0', 'label' => 'Không thực thi',  'description' => 'Gần như không có tiến triển đáng kể, hoặc gây ảnh hưởng tiêu cực. Tham khảo 0%.', 'score' => 0],
                    ['code' => 'B2-1', 'label' => 'Chưa đạt',        'description' => 'Không đạt mục tiêu, trễ hạn hoặc cần sửa nhiều (Unsatisfactory).',                 'score' => 1],
                    ['code' => 'B2-2', 'label' => 'Dưới chuẩn',      'description' => 'Hoàn thành một phần, trễ hạn hoặc cần sửa nhiều. Tham khảo 50–70%.',               'score' => 2],
                    ['code' => 'B2-3', 'label' => 'Cận chuẩn',       'description' => 'Đạt phần lớn yêu cầu nhưng còn thiếu tối ưu hoặc cần chỉnh sửa nhỏ. Tham khảo 70–90%.', 'score' => 3],
                    ['code' => 'B2-4', 'label' => 'Đạt chuẩn',       'description' => 'Đạt đầy đủ mục tiêu, đúng tiến độ, chất lượng đảm bảo. Tham khảo 90–110%.',       'score' => 4],
                    ['code' => 'B2-5', 'label' => 'Vượt chuẩn',      'description' => 'Hoàn thành vượt chỉ tiêu, vượt thời hạn, có cải tiến sáng tạo, tạo giá trị gia tăng rõ rệt. Tham khảo trên 110%.', 'score' => 5],
                ],
            ],

            // —— B3: Trừ điểm do sai sót (chủ quan) ——
            [
                'name'        => 'Sai sót / thiếu hiệu quả — nguyên nhân chủ quan',
                'type_code'   => 'CNTT-T02',
                'type'        => 'behavior',
                'description' => 'Mã B3. Trừ điểm do lỗi xuất phát từ bản thân người thực hiện công việc.',
                'allow_half'  => false,
                'levels'      => [
                    ['code' => 'B3-1', 'label' => 'Nhẹ',                 'description' => 'Thiếu tập trung, lơ đễnh, lỗi nhỏ, lỗi kỹ thuật đơn giản, khắc phục nhanh.',      'score' => -1],
                    ['code' => 'B3-2', 'label' => 'Trung bình',          'description' => 'Sai sót ảnh hưởng nội bộ, cần chỉnh sửa.',                                       'score' => -2],
                    ['code' => 'B3-3', 'label' => 'Nghiêm trọng',        'description' => 'Ảnh hưởng trễ deadline, vận hành.',                                              'score' => -3],
                    ['code' => 'B3-4', 'label' => 'Rất nghiêm trọng',    'description' => 'Gây tổn thất tài chính / uy tín, ảnh hưởng "khách hàng" nội bộ hoặc khách hàng.', 'score' => -4],
                    ['code' => 'B3-5', 'label' => 'Cực kỳ nghiêm trọng', 'description' => 'Lặp lại nhiều lần, thiếu trách nhiệm, gây khủng hoảng công ty, hệ thống, thương hiệu, uy tín.', 'score' => -5],
                ],
            ],

            // —— B4: Trừ điểm do sai sót (khách quan) ——
            [
                'name'        => 'Sai sót / thiếu hiệu quả — do cấp phát hiện',
                'type_code'   => 'CNTT-T02',
                'type'        => 'behavior',
                'description' => 'Mã B4. Trừ điểm theo cấp phát hiện/nhắc nhở sai sót, không phải bản thân tự nhận ra.',
                'allow_half'  => false,
                'levels'      => [
                    ['code' => 'B4-1', 'label' => 'Nhẹ',                 'description' => 'Tự bản thân nhận ra và khắc phục.',                                                    'score' => -1],
                    ['code' => 'B4-2', 'label' => 'Trung bình',          'description' => 'Tổ trưởng, Trưởng nhóm, Trưởng ca, Trưởng bộ phận (+ Phòng ban, Bộ phận khác) phát hiện.', 'score' => -2],
                    ['code' => 'B4-3', 'label' => 'Nghiêm trọng',        'description' => 'Trưởng phòng, Phó phòng, Hiệu trưởng, Hiệu phó (+ Phòng ban, Bộ phận khác) phát hiện.', 'score' => -3],
                    ['code' => 'B4-4', 'label' => 'Rất nghiêm trọng',    'description' => 'Giám đốc, Hội đồng phát hiện.',                                                        'score' => -4],
                    ['code' => 'B4-5', 'label' => 'Cực kỳ nghiêm trọng', 'description' => 'Ban Tổng Giám đốc phát hiện.',                                                         'score' => -5],
                ],
            ],

            // —— B5: Tốc độ khắc phục hậu quả ——
            [
                'name'        => 'Tốc độ khắc phục hậu quả',
                'type_code'   => 'CNTT-T02',
                'type'        => 'scale',
                'description' => 'Mã B5. Mức giảm trừ áp dụng cho tốc độ xử lý sau khi sai sót được ghi nhận.',
                'allow_half'  => false,
                'levels'      => [
                    ['code' => 'B5-1', 'label' => 'Trung bình',       'description' => 'Khắc phục ở tốc độ trung bình.', 'score' => 1],
                    ['code' => 'B5-2', 'label' => 'Rất nhanh kịp thời', 'description' => 'Khắc phục rất nhanh, kịp thời.', 'score' => 2],
                ],
            ],

            // —— A1: Chỉ số thái độ & tinh thần trách nhiệm ——
            [
                'name'        => 'Thái độ & tinh thần trách nhiệm',
                'type_code'   => 'CNTT-T03',
                'type'        => 'scale',
                'description' => 'Mã A1. Đánh giá tính cam kết, tính kỷ luật và mức độ chịu trách nhiệm đến cùng. Hệ số nhân tham khảo x0.80–x1.20.',
                'allow_half'  => false,
                'levels'      => [
                    ['code' => 'A1-1', 'label' => 'Chưa đạt',   'description' => 'Thiếu trách nhiệm, đùn đẩy, ảnh hưởng tập thể. Hệ số tham khảo x0.80.',            'score' => 1],
                    ['code' => 'A1-2', 'label' => 'Dưới chuẩn', 'description' => 'Hay viện lý do, cần nhắc nhở. Hệ số tham khảo x0.90.',                             'score' => 2],
                    ['code' => 'A1-3', 'label' => 'Cận chuẩn',  'description' => 'Làm đúng yêu cầu nhưng ít chủ động (tròn vai). Hệ số tham khảo x1.00.',            'score' => 3],
                    ['code' => 'A1-4', 'label' => 'Đạt chuẩn',  'description' => 'Nghiêm túc, làm việc có trách nhiệm, cầu tiến, dám bứt phá, làm mới. Hệ số tham khảo x1.10.', 'score' => 4],
                    ['code' => 'A1-5', 'label' => 'Vượt trội',  'description' => 'Chủ động nhận việc khó, không đổ lỗi, chịu trách nhiệm đến cùng. Hệ số tham khảo x1.20.', 'score' => 5],
                ],
            ],

            // —— A2: Điểm thưởng đột xuất ——
            [
                'name'        => 'Điểm thưởng đột xuất',
                'type_code'   => 'CNTT-T04',
                'type'        => 'behavior',
                'description' => 'Mã A2. Áp dụng khi có thành tích đặc biệt phát sinh ngoài công việc thường xuyên.',
                'allow_half'  => false,
                'levels'      => [
                    ['code' => 'A2-1', 'label' => 'Được khách hàng/đối tác khen ngợi chính thức',                       'description' => '', 'score' => 1],
                    ['code' => 'A2-2', 'label' => 'Giải quyết khủng hoảng kịp thời (yếu tố khách quan/bên ngoài)',      'description' => '', 'score' => 2],
                    ['code' => 'A2-3', 'label' => 'Triển khai ý tưởng dự án tạo giá trị hiệu quả',                      'description' => '', 'score' => 3],
                    ['code' => 'A2-4', 'label' => 'Đề xuất tiết kiệm chi phí đáng kể',                                  'description' => '', 'score' => 4],
                    ['code' => 'A2-5', 'label' => 'Ký được hợp đồng lớn / đạt doanh số vượt trội',                      'description' => '', 'score' => 5],
                ],
            ],

            // —— A3: Chủ động & sáng tạo ——
            [
                'name'        => 'Chủ động & sáng tạo',
                'type_code'   => 'CNTT-T05',
                'type'        => 'scale',
                'description' => 'Mã A3. Đánh giá khả năng cải tiến, đề xuất giải pháp mới.',
                'allow_half'  => false,
                'levels'      => [
                    ['code' => 'A3-1', 'label' => 'Chưa đạt',   'description' => 'Thụ động, không đổi mới.',                                                  'score' => 1],
                    ['code' => 'A3-2', 'label' => 'Dưới chuẩn', 'description' => 'Ít đề xuất.',                                                               'score' => 2],
                    ['code' => 'A3-3', 'label' => 'Cận chuẩn',  'description' => 'Có đóng góp nhỏ lẻ.',                                                       'score' => 3],
                    ['code' => 'A3-4', 'label' => 'Đạt chuẩn',  'description' => 'Thường xuyên đề xuất cải tiến hữu ích.',                                    'score' => 4],
                    ['code' => 'A3-5', 'label' => 'Vượt trội',  'description' => 'Có sáng kiến mang lại hiệu quả rõ rệt (doanh thu/tiết kiệm chi phí).',       'score' => 5],
                ],
            ],

            // —— A4: Teamwork top-down ——
            [
                'name'        => 'Teamwork — đánh giá top-down',
                'type_code'   => 'CNTT-T06',
                'type'        => 'scale',
                'description' => 'Mã A4. Đánh giá khả năng phối hợp và hỗ trợ đồng đội, do quản lý chấm.',
                'allow_half'  => false,
                'levels'      => [
                    ['code' => 'A4-1', 'label' => 'Chưa đạt',   'description' => 'Gây xung đột, ảnh hưởng tinh thần chung.',              'score' => 1],
                    ['code' => 'A4-2', 'label' => 'Dưới chuẩn', 'description' => 'Khó phối hợp.',                                         'score' => 2],
                    ['code' => 'A4-3', 'label' => 'Cận chuẩn',  'description' => 'Làm việc độc lập là chính.',                            'score' => 3],
                    ['code' => 'A4-4', 'label' => 'Đạt chuẩn',  'description' => 'Phối hợp tốt, tinh thần tích cực.',                     'score' => 4],
                    ['code' => 'A4-5', 'label' => 'Vượt trội',  'description' => 'Kết nối đội nhóm tốt, hỗ trợ người khác hoàn thành.',    'score' => 5],
                ],
            ],

            // —— A5: Teamwork 360 độ ——
            [
                'name'        => 'Teamwork — đánh giá 360 độ',
                'type_code'   => 'CNTT-T06',
                'type'        => 'scale',
                'description' => 'Mã A5. Đánh giá khả năng phối hợp và hỗ trợ đồng đội, tổng hợp từ nhiều phía (đồng nghiệp, phòng ban liên quan).',
                'allow_half'  => false,
                'levels'      => [
                    ['code' => 'A5-1', 'label' => 'Chưa đạt',   'description' => 'Gây xung đột, ảnh hưởng tinh thần chung.',              'score' => 1],
                    ['code' => 'A5-2', 'label' => 'Dưới chuẩn', 'description' => 'Khó phối hợp.',                                         'score' => 2],
                    ['code' => 'A5-3', 'label' => 'Cận chuẩn',  'description' => 'Làm việc độc lập là chính.',                            'score' => 3],
                    ['code' => 'A5-4', 'label' => 'Đạt chuẩn',  'description' => 'Phối hợp tốt, tinh thần tích cực.',                     'score' => 4],
                    ['code' => 'A5-5', 'label' => 'Vượt trội',  'description' => 'Kết nối đội nhóm tốt, hỗ trợ người khác hoàn thành.',    'score' => 5],
                ],
            ],
        ];
    }
}
