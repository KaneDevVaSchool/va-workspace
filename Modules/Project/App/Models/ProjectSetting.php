<?php

namespace Modules\Project\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cài đặt dự án dùng chung toàn hệ thống — CHỈ 1 dòng duy nhất áp dụng
 * (xem ProjectRepository — tự đảm bảo firstOrCreate luôn có đúng 1 dòng).
 *
 * @property int    $id
 * @property string $code_pattern
 * @property int    $code_counter
 * @property bool   $auto_start_on_begin_date
 * @property bool   $shift_task_dates_with_project
 * @property bool   $hide_cross_tasks_from_assignees
 * @property bool   $hide_child_tasks_from_followers
 * @property bool   $constrain_task_dates_to_project
 */
class ProjectSetting extends Model
{
    public const DEFAULT_CODE_PATTERN = 'DA_{date,"m/Y"}_{count}';

    public const DEFAULT_CODE_COUNTER = 344;

    protected $table = 'project_settings';

    protected $fillable = [
        'code_pattern',
        'code_counter',
        'auto_start_on_begin_date',
        'shift_task_dates_with_project',
        'hide_cross_tasks_from_assignees',
        'hide_child_tasks_from_followers',
        'constrain_task_dates_to_project',
    ];

    protected $casts = [
        'code_counter' => 'integer',
        'auto_start_on_begin_date' => 'boolean',
        'shift_task_dates_with_project' => 'boolean',
        'hide_cross_tasks_from_assignees' => 'boolean',
        'hide_child_tasks_from_followers' => 'boolean',
        'constrain_task_dates_to_project' => 'boolean',
    ];

    /** @return array<string, mixed> */
    public static function defaultAttributes(): array
    {
        return [
            'code_pattern' => self::DEFAULT_CODE_PATTERN,
            'code_counter' => self::DEFAULT_CODE_COUNTER,
            'auto_start_on_begin_date' => false,
            'shift_task_dates_with_project' => false,
            'hide_cross_tasks_from_assignees' => false,
            'hide_child_tasks_from_followers' => false,
            'constrain_task_dates_to_project' => false,
        ];
    }
}
