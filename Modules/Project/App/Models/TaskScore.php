<?php

namespace Modules\Project\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Đánh giá tối thiểu của Task — reference/phiếu chấm đơn giản, KHÔNG phải
 * TaskScoringConfig/Kpi đầy đủ (Phase 4, chưa dựng — xem
 * docs/VA_WORKSPACE_OVERVIEW.md §7). 1 task chỉ có 1 bản ghi hiện hành
 * (unique task_id trong migration) — update ghi đè, không lưu lịch sử.
 *
 * rating_result là text tự do (KHÔNG enum) — kết quả đánh giá tuỳ cấu hình
 * evaluation tương lai. is_passed là field riêng, tường minh Đạt/Không đạt
 * (không suy luận từ text rating_result) — is_passed = false sẽ tăng
 * tasks.failed_review_count (xem TaskScoreService::upsert()).
 *
 * @property int $id
 * @property int $task_id
 * @property float|null $rating_score
 * @property string|null $rating_result
 * @property bool|null $is_passed
 * @property string|null $rating_desc
 * @property int|null $scored_by
 * @property string|null $scored_at
 */
class TaskScore extends Model
{
    protected $table = 'task_scores';

    protected $fillable = [
        'task_id',
        'rating_score',
        'rating_result',
        'is_passed',
        'rating_desc',
        'scored_by',
        'scored_at',
    ];

    protected $casts = [
        'rating_score' => 'decimal:2',
        'is_passed' => 'boolean',
        'scored_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function scorer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scored_by');
    }
}
