<?php

namespace Modules\Report\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một nhân sự trong bản chụp phạm vi của báo cáo đã lưu.
 *
 * Tên lưu ngay tại đây chứ không đọc qua quan hệ, để báo cáo cũ vẫn đọc được
 * khi tài khoản đã bị xoá.
 *
 * @property int    $id
 * @property int    $report_id
 * @property int    $user_id
 * @property string $user_name
 * @property int    $sort_order
 */
class ReportPersonSnapshot extends Model
{
    protected $table = 'report_people_snapshots';

    protected $fillable = ['report_id', 'user_id', 'user_name', 'sort_order'];

    protected $casts = [
        'user_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
