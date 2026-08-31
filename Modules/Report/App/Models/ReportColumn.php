<?php

namespace Modules\Report\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cột được bật trong bảng báo cáo.
 *
 * @property int    $id
 * @property int    $report_id
 * @property string $column_key
 * @property int    $sort_order
 */
class ReportColumn extends Model
{
    protected $table = 'report_columns';

    protected $fillable = ['report_id', 'column_key', 'sort_order'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
