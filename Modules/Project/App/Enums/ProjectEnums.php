<?php

namespace Modules\Project\App\Enums;

/**
 * Danh mục giá trị cố định (enum-string) của module Project — theo đúng
 * convention const-array đã dùng ở các module khác (danh sách giá trị hợp
 * lệ + nhãn tiếng Việt trong 1 const array), không dùng PHP enum (repo chưa
 * có tiền lệ PHP enum ở module nào khác).
 *
 * Export ra JSON cho frontend qua ProjectController::options() —
 * GET /api/project/options.
 */
class ProjectEnums
{
    // Loại dự án KHÔNG còn là enum tĩnh — đã chuyển sang danh mục lưu DB
    // (bảng project_types, Modules\Project\App\Models\ProjectType) để người
    // dùng tự tạo loại mới ngay trong form (mục A). Xem
    // ProjectService::allTypes()/createType(), route GET/POST /api/project/types.

    /** Trạng thái dự án. */
    public const STATUSES = ['planning', 'in_progress', 'on_hold', 'completed', 'cancelled'];

    public const STATUS_LABELS = [
        'planning' => 'Lên kế hoạch',
        'in_progress' => 'Đang thực hiện',
        'on_hold' => 'Tạm dừng',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã huỷ',
    ];

    /** Mức độ quan trọng — 5 bậc kèm trọng số. */
    public const IMPORTANCE_LEVELS = ['support', 'assist', 'important', 'high_priority', 'strategic'];

    public const IMPORTANCE_LABELS = [
        'support' => 'Phụ trợ',
        'assist' => 'Hỗ trợ',
        'important' => 'Quan trọng',
        'high_priority' => 'Ưu tiên cao',
        'strategic' => 'Chiến lược / Sống còn',
    ];

    public const IMPORTANCE_DESCRIPTIONS = [
        'support' => 'Tác động nhỏ. Trọng số x1.',
        'assist' => 'Tác động gián tiếp. Trọng số x2.',
        'important' => 'Ảnh hưởng đến vận hành thường xuyên, trực tiếp. Trọng số x3.',
        'high_priority' => 'Ảnh hưởng đến kết quả phòng ban chức năng, hoặc khách hàng nội bộ/bên ngoài. Trọng số x4.',
        'strategic' => 'Ảnh hưởng trực tiếp đến thương hiệu, pháp lý, định hướng dài hạn, doanh thu, uy tín công ty. Trọng số x5.',
    ];

    public const IMPORTANCE_WEIGHTS = [
        'support' => 1,
        'assist' => 2,
        'important' => 3,
        'high_priority' => 4,
        'strategic' => 5,
    ];

    /** Nhãn cũ (và key cũ) vẫn nhận khi nhập Excel / dữ liệu tồn tại. */
    public const IMPORTANCE_ALIASES = [
        'low' => 'support',
        'medium' => 'important',
        'high' => 'high_priority',
        'critical' => 'strategic',
        'thấp' => 'support',
        'trung bình' => 'important',
        'cao' => 'high_priority',
        'rất quan trọng' => 'strategic',
        'phụ trợ' => 'support',
        'b1-1 phụ trợ' => 'support',
        'b1-1' => 'support',
        'hỗ trợ' => 'assist',
        'b1-2 hỗ trợ' => 'assist',
        'b1-2' => 'assist',
        'quan trọng' => 'important',
        'b1-3 quan trọng' => 'important',
        'b1-3' => 'important',
        'ưu tiên cao' => 'high_priority',
        'b1-4 ưu tiên cao' => 'high_priority',
        'b1-4' => 'high_priority',
        'chiến lược' => 'strategic',
        'sống còn' => 'strategic',
        'chiến lược / sống còn' => 'strategic',
        'b1-5 chiến lược / sống còn' => 'strategic',
        'b1-5' => 'strategic',
    ];

    /** Phương pháp tính tiến độ. */
    public const PROGRESS_METHODS = ['average', 'duration_weighted', 'task_weighted'];

    public const PROGRESS_METHOD_LABELS = [
        'average' => 'Theo bình quân % tiến độ các công việc thuộc dự án',
        'duration_weighted' => 'Theo tỷ trọng ngày thực hiện',
        'task_weighted' => 'Theo tỷ trọng công việc',
    ];

    public const PROGRESS_METHOD_DESCRIPTIONS = [
        'average' => 'Ví dụ dự án gồm 2 công việc A và B. Công việc A tiến độ 40%, công việc B tiến độ 60%. Tiến độ dự án là (60+40)/2 = 50%',
        'duration_weighted' => 'Ví dụ dự án gồm 2 công việc A và B. Công việc A yêu cầu thực hiện trong 4 ngày, tiến độ 40% Công việc B yêu cầu thực hiện trong 6 ngày, tiến độ 50% Tiến độ dự án là ((4*40 + 6*50)/(4*100 + 6*100)) * 100 = 46%',
        'task_weighted' => 'Ví dụ Dự án gồm 2 công việc A và B Công việc A có Tỷ trọng là 40, tiến độ là 50% Công việc B có Tỷ trọng là 30, tiến độ là 40% Tiến độ của dự án là [(40x50)+(30x40)]/(40+30)=45.71%',
    ];

    /** Phạm vi triển khai — mỗi dự án chỉ chọn 1. */
    public const SCOPE_TYPES = ['head_office_bt_llq', 'ht', 'kv', 'department'];

    public const SCOPE_TYPE_LABELS = [
        'head_office_bt_llq' => 'Hội Sở (Bình Thới & Tân Bình)',
        'ht' => 'Toàn Hệ Thống',
        'kv' => 'Khu Vực (Sài Gòn - Cần Thơ - Vũng Tàu)',
        'department' => 'Phòng Ban/Bộ Phận',
    ];

    /** Danh sách options đầy đủ trả về cho frontend — GET /api/project/options. */
    public static function options(): array
    {
        return [
            'status' => self::mapOptions(self::STATUSES, self::STATUS_LABELS),
            'importance' => self::mapImportanceOptions(),
            'progress_method' => self::mapOptions(
                self::PROGRESS_METHODS,
                self::PROGRESS_METHOD_LABELS,
                self::PROGRESS_METHOD_DESCRIPTIONS,
            ),
            'scope_type' => self::mapOptions(self::SCOPE_TYPES, self::SCOPE_TYPE_LABELS),
        ];
    }

    /** Nhận key tiếng Anh hoặc nhãn tiếng Việt (không phân biệt hoa thường) → key, hoặc null. */
    public static function statusFromInput(string $input): ?string
    {
        return self::valueFromInput($input, self::STATUSES, self::STATUS_LABELS);
    }

    public static function importanceFromInput(string $input): ?string
    {
        $direct = self::valueFromInput($input, self::IMPORTANCE_LEVELS, self::IMPORTANCE_LABELS);
        if ($direct !== null) {
            return $direct;
        }

        $lower = mb_strtolower(trim($input));
        if ($lower === '') {
            return null;
        }

        return self::IMPORTANCE_ALIASES[$lower] ?? null;
    }

    public static function progressMethodFromInput(string $input): ?string
    {
        $direct = self::valueFromInput($input, self::PROGRESS_METHODS, self::PROGRESS_METHOD_LABELS);
        if ($direct !== null) {
            return $direct;
        }

        $aliases = [
            'bình quân % hoàn thành' => 'average',
            'theo bình quân % tiến độ các công việc' => 'average',
        ];

        return $aliases[mb_strtolower(trim($input))] ?? null;
    }

    /** @return list<array{value: string, label: string, description: string, weight: int}> */
    private static function mapImportanceOptions(): array
    {
        return array_map(function (string $value) {
            return [
                'value' => $value,
                'label' => self::IMPORTANCE_LABELS[$value] ?? $value,
                'description' => self::IMPORTANCE_DESCRIPTIONS[$value] ?? '',
                'weight' => self::IMPORTANCE_WEIGHTS[$value] ?? 0,
            ];
        }, self::IMPORTANCE_LEVELS);
    }

    /** @return list<array{value: string, label: string, description?: string}> */
    private static function mapOptions(array $values, array $labels, ?array $descriptions = null): array
    {
        return array_map(function (string $value) use ($labels, $descriptions) {
            $row = ['value' => $value, 'label' => $labels[$value] ?? $value];
            if ($descriptions !== null) {
                $row['description'] = $descriptions[$value] ?? '';
            }

            return $row;
        }, $values);
    }

    /** @param  list<string>  $values */
    private static function valueFromInput(string $input, array $values, array $labels): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        $lower = mb_strtolower($input);
        foreach ($values as $value) {
            if (mb_strtolower($value) === $lower) {
                return $value;
            }
        }

        foreach ($labels as $value => $label) {
            if (mb_strtolower((string) $label) === $lower) {
                return $value;
            }
        }

        return null;
    }
}
