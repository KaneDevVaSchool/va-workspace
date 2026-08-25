<?php

namespace Modules\Evaluation\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Models\EvaluationCriterionType;
use Modules\Identity\App\Models\Department;

/**
 * Loại + tiêu chí đánh giá mẫu theo từng phòng ban.
 * Idempotent: updateOrCreate theo (department_id, code/name).
 */
class EvaluationCriteriaSeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::query()->orderBy('id')->get();
        if ($departments->isEmpty()) {
            return;
        }

        $createdBy = User::query()->orderBy('id')->value('id');

        foreach ($departments as $department) {
            $this->seedForDepartment((int) $department->id, $createdBy);
        }
    }

    private function seedForDepartment(int $departmentId, ?int $createdBy): void
    {
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
                    'is_active'         => $criterion['is_active'],
                    'allow_half'        => $criterion['allow_half'],
                    'sort_order'        => $index,
                    'created_by'        => $createdBy,
                ],
            );
        }
    }

    /** @return list<array{code: string, name: string, description: string}> */
    private function types(): array
    {
        return [
            [
                'code'        => 'TCA0001',
                'name'        => 'Năng lực chuyên môn',
                'description' => 'Kiến thức, kỹ năng và chất lượng hoàn thành công việc.',
            ],
            [
                'code'        => 'TCA0002',
                'name'        => 'Thái độ làm việc',
                'description' => 'Trách nhiệm, phối hợp và tinh thần chủ động.',
            ],
            [
                'code'        => 'TCA0003',
                'name'        => 'Kỷ luật & hành vi',
                'description' => 'Chuyên cần, nội quy và các hành vi cộng/trừ điểm.',
            ],
        ];
    }

    /**
     * @return list<array{
     *     name: string,
     *     type_code: string,
     *     type: string,
     *     description: string,
     *     is_active: bool,
     *     allow_half: bool,
     *     levels: list<array{code: string, label: string, description: string, score: float|int}>
     * }>
     */
    private function criteria(): array
    {
        $scale = $this->scaleLevels();

        return [
            [
                'name'        => 'Chất lượng công việc',
                'type_code'   => 'TCA0001',
                'type'        => 'scale',
                'description' => 'Độ chính xác, hoàn thiện và đạt yêu cầu chuyên môn.',
                'is_active'   => true,
                'allow_half'  => false,
                'levels'      => $scale,
            ],
            [
                'name'        => 'Đúng hạn & tiến độ',
                'type_code'   => 'TCA0001',
                'type'        => 'scale',
                'description' => 'Hoàn thành đúng hạn, chủ động báo khi có rủi ro chậm.',
                'is_active'   => true,
                'allow_half'  => true,
                'levels'      => [
                    ['code' => 'M1', 'label' => 'Thường xuyên chậm',     'description' => '', 'score' => 1],
                    ['code' => 'M2', 'label' => 'Đôi khi chậm',          'description' => '', 'score' => 2],
                    ['code' => 'M3', 'label' => 'Đúng hạn',              'description' => '', 'score' => 3],
                    ['code' => 'M4', 'label' => 'Sớm hơn kế hoạch',      'description' => '', 'score' => 4],
                    ['code' => 'M5', 'label' => 'Luôn vượt tiến độ',     'description' => '', 'score' => 5],
                ],
            ],
            [
                'name'        => 'Mức độ độc lập',
                'type_code'   => 'TCA0001',
                'type'        => 'scale',
                'description' => 'Tự xử lý công việc, hạn chế phụ thuộc hướng dẫn. Cho phép chấm 0.5.',
                'is_active'   => true,
                'allow_half'  => true,
                'levels'      => [
                    ['code' => 'M1', 'label' => 'Cần hướng dẫn liên tục', 'description' => '', 'score' => 0.5],
                    ['code' => 'M2', 'label' => 'Cần hỗ trợ thường xuyên', 'description' => '', 'score' => 1],
                    ['code' => 'M3', 'label' => 'Tự làm việc quen',       'description' => '', 'score' => 1.5],
                    ['code' => 'M4', 'label' => 'Chủ động hầu hết việc',  'description' => '', 'score' => 2],
                    ['code' => 'M5', 'label' => 'Hoàn toàn độc lập',      'description' => '', 'score' => 2.5],
                ],
            ],
            [
                'name'        => 'Giao tiếp & phối hợp',
                'type_code'   => 'TCA0002',
                'type'        => 'scale',
                'description' => 'Phản hồi rõ, phối hợp tốt trong nhóm và với phòng ban khác.',
                'is_active'   => true,
                'allow_half'  => false,
                'levels'      => $scale,
            ],
            [
                'name'        => 'Tinh thần trách nhiệm',
                'type_code'   => 'TCA0002',
                'type'        => 'scale',
                'description' => 'Nhận việc, theo đến cùng, không đùn đẩy.',
                'is_active'   => true,
                'allow_half'  => false,
                'levels'      => $scale,
            ],
            [
                'name'        => 'Chuyên cần',
                'type_code'   => 'TCA0003',
                'type'        => 'behavior',
                'description' => 'Cộng/trừ theo đúng giờ, vắng mặt và hoàn thành sớm.',
                'is_active'   => true,
                'allow_half'  => false,
                'levels'      => [
                    ['code' => 'H1', 'label' => 'Đi muộn',            'description' => 'Mỗi lần đi muộn',           'score' => -1],
                    ['code' => 'H2', 'label' => 'Vắng không phép',    'description' => 'Không báo trước',            'score' => -2],
                    ['code' => 'H3', 'label' => 'Hoàn thành sớm',     'description' => 'Nộp việc trước hạn',         'score' => 1],
                    ['code' => 'H4', 'label' => 'Hỗ trợ đồng nghiệp', 'description' => 'Nhận thêm việc khi nhóm cần', 'score' => 2],
                ],
            ],
            [
                'name'        => 'Đóng góp tích cực',
                'type_code'   => 'TCA0003',
                'type'        => 'behavior',
                'description' => 'Hành vi cộng điểm; cho phép bước 0.5.',
                'is_active'   => true,
                'allow_half'  => true,
                'levels'      => [
                    ['code' => 'H1', 'label' => 'Đề xuất cải tiến',     'description' => 'Ý tưởng được áp dụng',     'score' => 1.5],
                    ['code' => 'H2', 'label' => 'Hướng dẫn người mới',  'description' => 'Kèm cặp đồng nghiệp mới',  'score' => 1],
                    ['code' => 'H3', 'label' => 'Vi phạm nội quy',      'description' => 'Không chấp hành quy định', 'score' => -1.5],
                    ['code' => 'H4', 'label' => 'Gây chậm tiến độ nhóm','description' => 'Làm ảnh hưởng người khác', 'score' => -0.5],
                ],
            ],
            [
                'name'        => 'Tiêu chí thử nghiệm',
                'type_code'   => 'TCA0002',
                'type'        => 'scale',
                'description' => 'Bản nháp — không dùng khi chấm. Để kiểm tra trạng thái Không hoạt động.',
                'is_active'   => false,
                'allow_half'  => false,
                'levels'      => $scale,
            ],
        ];
    }

    /** @return list<array{code: string, label: string, description: string, score: int}> */
    private function scaleLevels(): array
    {
        return [
            ['code' => 'M1', 'label' => 'Không đáp ứng',   'description' => '', 'score' => 1],
            ['code' => 'M2', 'label' => 'Cần cố gắng hơn', 'description' => '', 'score' => 2],
            ['code' => 'M3', 'label' => 'Đạt yêu cầu',     'description' => '', 'score' => 3],
            ['code' => 'M4', 'label' => 'Tốt',             'description' => '', 'score' => 4],
            ['code' => 'M5', 'label' => 'Rất tốt',         'description' => '', 'score' => 5],
        ];
    }
}
