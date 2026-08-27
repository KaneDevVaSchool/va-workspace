<?php

namespace Modules\Project\App\Enums;

/**
 * Danh mục giá trị cố định (enum-string) của module Project — theo đúng
 * convention const-array đã dùng ở Evaluation (xem
 * EvaluationTemplateCustomField::FIELD_TYPES / TYPE_LABELS), không dùng
 * PHP enum (repo chưa có tiền lệ PHP enum ở module nào khác).
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
        'chiến lược' => 'strategic',
        'sống còn' => 'strategic',
        'chiến lược / sống còn' => 'strategic',
    ];

    /** Phương pháp tính tiến độ. */
    public const PROGRESS_METHODS = ['average', 'duration_weighted', 'task_weighted'];

    public const PROGRESS_METHOD_LABELS = [
        'average' => 'Bình quân % hoàn thành',
        'duration_weighted' => 'Theo tỷ trọng ngày thực hiện',
        'task_weighted' => 'Theo tỷ trọng công việc',
    ];

    public const PROGRESS_METHOD_DESCRIPTIONS = [
        'average' => 'Lấy trung bình cộng phần trăm hoàn thành của tất cả công việc trong dự án.',
        'duration_weighted' => 'Công việc kéo dài nhiều ngày hơn sẽ ảnh hưởng nhiều hơn đến tiến độ chung.',
        'task_weighted' => 'Mỗi công việc được gán một tỷ trọng riêng, tiến độ chung tính theo tỷ trọng đó.',
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
        return self::valueFromInput($input, self::PROGRESS_METHODS, self::PROGRESS_METHOD_LABELS);
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
