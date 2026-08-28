<?php

namespace Modules\Project\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Worklog chấm công giờ thực tế — mỗi người chỉ tự ghi giờ của chính mình
 * (không chấm hộ), user_id luôn = người đăng nhập (ép ở TaskWorklogService).
 * KHÔNG có rate_snapshot/chi phí — thuộc ProjectFinance (chưa dựng).
 *
 * @property int         $id
 * @property int         $task_id
 * @property int         $user_id
 * @property string      $work_date
 * @property float        $hours
 * @property string|null $note
 * @property int|null    $created_by
 */
class TaskWorklog extends Model
{
    protected $table = 'task_worklogs';

    protected $fillable = [
        'task_id',
        'user_id',
        'work_date',
        'hours',
        'note',
        'created_by',
    ];

    protected $casts = [
        'work_date' => 'date',
        'hours' => 'decimal:2',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
