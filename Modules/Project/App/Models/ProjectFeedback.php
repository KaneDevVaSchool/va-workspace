<?php

namespace Modules\Project\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phản hồi/đánh giá tổng thể kết quả dự án (tab "Phản hồi" — tuỳ chọn
 * bật/tắt theo từng dự án). Khác Comment (Thảo luận công việc hằng ngày):
 * list phẳng, không thread/reaction/mention, chỉ tác giả sửa/xoá được
 * (xem ProjectFeedbackService::canEdit()).
 *
 * @property int $id
 * @property int $project_id
 * @property int $author_id
 * @property int|null $rating 1-5, tuỳ chọn
 * @property string $content
 */
class ProjectFeedback extends Model
{
    protected $table = 'project_feedbacks';

    protected $fillable = [
        'project_id',
        'author_id',
        'rating',
        'content',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
