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
    /** Loại dự án. */
    public const TYPES = ['internal', 'customer', 'infrastructure', 'research', 'other'];

    public const TYPE_LABELS = [
        'internal' => 'Nội bộ',
        'customer' => 'Khách hàng',
        'infrastructure' => 'Hạ tầng',
        'research' => 'Nghiên cứu',
        'other' => 'Khác',
    ];

    /** Trạng thái dự án. */
    public const STATUSES = ['planning', 'in_progress', 'on_hold', 'completed', 'cancelled'];

    public const STATUS_LABELS = [
        'planning' => 'Lên kế hoạch',
        'in_progress' => 'Đang thực hiện',
        'on_hold' => 'Tạm dừng',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã huỷ',
    ];

    /** Mức độ quan trọng. */
    public const IMPORTANCE_LEVELS = ['low', 'medium', 'high', 'critical'];

    public const IMPORTANCE_LABELS = [
        'low' => 'Thấp',
        'medium' => 'Trung bình',
        'high' => 'Cao',
        'critical' => 'Rất quan trọng',
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

    /** Phạm vi triển khai (project_scopes.scope_type). */
    public const SCOPE_TYPES = ['head_office_bt_llq', 'ht', 'kv', 'department'];

    public const SCOPE_TYPE_LABELS = [
        'head_office_bt_llq' => 'Hội Sở (BT - LLQ)',
        'ht' => 'HT',
        'kv' => 'KV',
        'department' => 'Phòng Ban',
    ];

    /** Danh sách options đầy đủ trả về cho frontend — GET /api/project/options. */
    public static function options(): array
    {
        return [
            'type' => self::mapOptions(self::TYPES, self::TYPE_LABELS),
            'status' => self::mapOptions(self::STATUSES, self::STATUS_LABELS),
            'importance' => self::mapOptions(self::IMPORTANCE_LEVELS, self::IMPORTANCE_LABELS),
            'progress_method' => self::mapOptions(
                self::PROGRESS_METHODS,
                self::PROGRESS_METHOD_LABELS,
                self::PROGRESS_METHOD_DESCRIPTIONS,
            ),
            'scope_type' => self::mapOptions(self::SCOPE_TYPES, self::SCOPE_TYPE_LABELS),
        ];
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
}
