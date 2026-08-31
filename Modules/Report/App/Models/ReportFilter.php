<?php

namespace Modules\Report\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một điều kiện thu hẹp phạm vi dữ liệu của báo cáo (hiện dùng `user_id`).
 *
 * @property int    $id
 * @property int    $report_id
 * @property string $filter_key
 * @property string $filter_value
 */
class ReportFilter extends Model
{
    protected $table = 'report_filters';

    protected $fillable = ['report_id', 'filter_key', 'filter_value'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
