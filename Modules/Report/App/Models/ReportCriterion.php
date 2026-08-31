<?php

namespace Modules\Report\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Evaluation\App\Models\EvaluationCriteria;

/**
 * Tiêu chí đánh giá được chọn hiển thị trong báo cáo.
 *
 * @property int      $id
 * @property int      $report_id
 * @property int|null $criterion_id
 */
class ReportCriterion extends Model
{
    protected $table = 'report_criteria';

    protected $fillable = ['report_id', 'criterion_id'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriteria::class, 'criterion_id');
    }
}
