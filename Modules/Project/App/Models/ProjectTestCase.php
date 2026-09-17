<?php

namespace Modules\Project\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Testcase kiểm thử thuộc 1 dự án (tab "Testcase" — tuỳ chọn bật/tắt theo
 * từng dự án, xem Project::$disabled_tabs).
 *
 * Nghiệm thu 2 lượt: Check lần 1 do assignee tự chấm, Check lần 2 do
 * người tạo testcase (created_by) chấm — xem
 * ProjectTestCaseService::updateCheck1()/updateCheck2().
 *
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property string|null $steps
 * @property string|null $expected_result
 * @property string|null $actual_result
 * @property string $check1_status pending | passed | failed
 * @property int|null $check1_by
 * @property \Illuminate\Support\Carbon|null $check1_at
 * @property string $check2_status pending | passed | failed
 * @property int|null $check2_by
 * @property \Illuminate\Support\Carbon|null $check2_at
 * @property string|null $link_url
 * @property string|null $attachment_path
 * @property int|null $assignee_id
 * @property int|null $phase_id giai đoạn (tasks.type=phase) cùng dự án — tổng hợp testcase theo module
 * @property int $sort_order
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class ProjectTestCase extends Model
{
    protected $table = 'project_test_cases';

    protected $fillable = [
        'project_id',
        'title',
        'steps',
        'expected_result',
        'actual_result',
        'check1_status',
        'check1_by',
        'check1_at',
        'check2_status',
        'check2_by',
        'check2_at',
        'link_url',
        'attachment_path',
        'assignee_id',
        'phase_id',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'check1_at' => 'datetime',
        'check2_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'phase_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function check1Reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'check1_by');
    }

    public function check2Reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'check2_by');
    }
}
