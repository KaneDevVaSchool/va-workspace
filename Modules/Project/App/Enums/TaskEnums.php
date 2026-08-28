<?php

namespace Modules\Project\App\Enums;

/**
 * Danh mục giá trị cố định (enum-string) của entity Task — theo đúng
 * convention const-array của ProjectEnums (không dùng PHP enum).
 *
 * Export ra JSON cho frontend qua TaskController::options() —
 * GET /api/project/tasks/options.
 */
class TaskEnums
{
    /**
     * Loại node trong cây WBS — 'task' là công việc thật (có assignee/tiến
     * độ), 'phase'/'category' là node cha nhóm công việc (không assignee).
     * Không tách bảng phases/task_categories riêng — xem quyết định QD1/QD2
     * trong plan Project Giai đoạn 2.
     */
    public const TYPES = ['task', 'phase', 'category'];

    public const TYPE_LABELS = [
        'task' => 'Công việc',
        'phase' => 'Giai đoạn',
        'category' => 'Danh mục',
    ];

    /** Trạng thái công việc — cố ý khác bộ giá trị ProjectEnums::STATUSES. */
    public const STATUSES = ['not_started', 'in_progress', 'on_hold', 'completed', 'cancelled'];

    public const STATUS_LABELS = [
        'not_started' => 'Chưa bắt đầu',
        'in_progress' => 'Đang thực hiện',
        'on_hold' => 'Tạm dừng',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã huỷ',
    ];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public const PRIORITY_LABELS = [
        'low' => 'Thấp',
        'medium' => 'Trung bình',
        'high' => 'Cao',
        'urgent' => 'Khẩn cấp',
    ];

    /**
     * Cách tính tiến độ — 'percent' nhập tay progress_percent trực tiếp (mặc
     * định, hành vi hiện có); 'quantity' tự tính progress_percent từ
     * progress_number/progress_total (TaskService), không nhập tay % trực tiếp.
     */
    public const PROGRESS_TYPES = ['percent', 'quantity'];

    public const PROGRESS_TYPE_LABELS = [
        'percent' => 'Theo phần trăm',
        'quantity' => 'Theo khối lượng',
    ];

    /** Danh sách options đầy đủ trả về cho frontend — GET /api/project/tasks/options. */
    public static function options(): array
    {
        return [
            'type' => self::mapOptions(self::TYPES, self::TYPE_LABELS),
            'status' => self::mapOptions(self::STATUSES, self::STATUS_LABELS),
            'priority' => self::mapOptions(self::PRIORITIES, self::PRIORITY_LABELS),
            'progress_type' => self::mapOptions(self::PROGRESS_TYPES, self::PROGRESS_TYPE_LABELS),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private static function mapOptions(array $values, array $labels): array
    {
        return array_map(function (string $value) use ($labels) {
            return ['value' => $value, 'label' => $labels[$value] ?? $value];
        }, $values);
    }

    /**
     * Đối chiếu input tiếng Việt (Excel import — PR8) ngược lại về value
     * enum — nhận cả value gốc lẫn label, không phân biệt hoa/thường.
     * Cùng pattern ProjectEnums::valueFromInput().
     */
    public static function typeFromInput(string $input): ?string
    {
        return self::valueFromInput($input, self::TYPES, self::TYPE_LABELS);
    }

    public static function statusFromInput(string $input): ?string
    {
        return self::valueFromInput($input, self::STATUSES, self::STATUS_LABELS);
    }

    public static function priorityFromInput(string $input): ?string
    {
        return self::valueFromInput($input, self::PRIORITIES, self::PRIORITY_LABELS);
    }

    public static function progressTypeFromInput(string $input): ?string
    {
        return self::valueFromInput($input, self::PROGRESS_TYPES, self::PROGRESS_TYPE_LABELS);
    }

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
