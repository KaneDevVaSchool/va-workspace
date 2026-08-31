<?php

namespace Modules\Report\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Người được cấp quyền xem một báo cáo.
 *
 * @property int $id
 * @property int $report_id
 * @property int $user_id
 */
class ReportViewer extends Model
{
    protected $table = 'report_viewers';

    protected $fillable = ['report_id', 'user_id'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
