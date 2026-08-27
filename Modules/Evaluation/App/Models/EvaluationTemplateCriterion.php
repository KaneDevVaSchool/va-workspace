<?php

namespace Modules\Evaluation\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dòng N-N giữa EvaluationTemplate và EvaluationCriteria, mang trọng số
 * riêng theo từng mẫu (1 tiêu chí có thể có trọng số khác nhau ở mỗi mẫu).
 *
 * weight_percent là % trọng số của tiêu chí trong mẫu (bước 10, 10-100).
 * Chỉ các dòng count_in_total = true cộng vào tổng điểm — tổng trọng số
 * của nhóm đó phải bằng 100. Dòng không tính vào tổng điểm lưu weight = 0.
 *
 * @property int    $id
 * @property int    $evaluation_template_id
 * @property int    $evaluation_criteria_id
 * @property int    $weight_percent
 * @property int|null $required_score
 * @property bool   $count_in_total
 * @property int    $sort_order
 */
class EvaluationTemplateCriterion extends Model
{
    protected $table = 'evaluation_template_criteria';

    protected $fillable = [
        'evaluation_template_id',
        'evaluation_criteria_id',
        'weight_percent',
        'required_score',
        'count_in_total',
        'sort_order',
    ];

    protected $casts = [
        'weight_percent' => 'integer',
        'count_in_total' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'evaluation_template_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriteria::class, 'evaluation_criteria_id');
    }
}
