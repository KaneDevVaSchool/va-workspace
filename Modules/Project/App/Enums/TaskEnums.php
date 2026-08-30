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

    /**
     * Mức độ quan trọng — cùng 5 bậc với ProjectEnums / tiêu chí B1.
     * Key cũ (low/medium/high/urgent) vẫn nhận khi đọc dữ liệu tồn tại.
     */
    public const PRIORITIES = ['support', 'assist', 'important', 'high_priority', 'strategic'];

    public const PRIORITY_LABELS = [
        'support' => 'Phụ trợ',
        'assist' => 'Hỗ trợ',
        'important' => 'Quan trọng',
        'high_priority' => 'Ưu tiên cao',
        'strategic' => 'Chiến lược / Sống còn',
        'low' => 'Thấp',
        'medium' => 'Trung bình',
        'high' => 'Cao',
        'urgent' => 'Khẩn cấp',
    ];

    public const PRIORITY_ALIASES = [
        'low' => 'support',
        'medium' => 'important',
        'high' => 'high_priority',
        'urgent' => 'strategic',
    ];

    /**
     * Cách tính tiến độ. `percent` và `quantity` giữ nguyên value cũ để tương
     * thích dữ liệu; các phương pháp còn lại lấy dữ liệu từ cấu trúc công việc.
     */
    public const PROGRESS_TYPES = ['percent', 'quantity', 'checklist', 'child_weight', 'timeline'];

    public const PROGRESS_TYPE_LABELS = [
        'percent' => 'Theo % người dùng tự cập nhật',
        'quantity' => 'Theo tỷ lệ hoàn thành khối lượng công việc',
        'checklist' => 'Theo tỷ lệ hoàn thành đầu việc',
        'child_weight' => 'Theo tỷ trọng công việc con',
        'timeline' => 'Tự động theo thời gian hoàn thành công việc',
    ];

    public const COMPLETED_INTERACTION_POLICIES = ['allow', 'deny', 'inherit'];

    public const REPORT_REQUIREMENTS = ['none', 'on_report', 'on_completion'];

    public const DELEGATION_STATUSES = ['pending', 'accepted', 'in_progress', 'done', 'rejected'];

    public const DELEGATION_STATUS_LABELS = [
        'pending' => 'Chờ tiếp nhận',
        'accepted' => 'Đã tiếp nhận',
        'in_progress' => 'Đang thực hiện',
        'done' => 'Hoàn thành',
        'rejected' => 'Từ chối',
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
        $direct = self::valueFromInput($input, self::PRIORITIES, self::PRIORITY_LABELS);
        if ($direct !== null) {
            return self::PRIORITY_ALIASES[$direct] ?? $direct;
        }

        $lower = mb_strtolower(trim($input));
        if ($lower !== '' && isset(self::PRIORITY_ALIASES[$lower])) {
            return self::PRIORITY_ALIASES[$lower];
        }

        return ProjectEnums::importanceFromInput($input);
    }

    public static function normalizePriority(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        return self::priorityFromInput($input);
    }

    public static function priorityLabel(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $canonical = self::PRIORITY_ALIASES[$value] ?? $value;

        return self::PRIORITY_LABELS[$canonical]
            ?? self::PRIORITY_LABELS[$value]
            ?? ProjectEnums::IMPORTANCE_LABELS[$canonical]
            ?? $value;
    }

    /** @return list<string> */
    public static function acceptedPriorities(): array
    {
        return array_values(array_unique(array_merge(
            self::PRIORITIES,
            array_keys(self::PRIORITY_ALIASES),
            array_keys(self::PRIORITY_LABELS),
            ['B1-1', 'B1-2', 'B1-3', 'B1-4', 'B1-5', 'b1-1', 'b1-2', 'b1-3', 'b1-4', 'b1-5'],
        )));
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
