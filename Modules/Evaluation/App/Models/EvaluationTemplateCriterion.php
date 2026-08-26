<?php

namespace Modules\Evaluation\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dòng N-N giữa EvaluationTemplate và EvaluationCriteria, mang trọng số
 * riêng theo từng mẫu (1 tiêu chí có thể có trọng số khác nhau ở mỗi mẫu).
 *
 * weight_label hiển thị chữ tiếng Việt phổ thông (CLAUDE.md §14); weight_value
 * là số ẩn phía sau dùng để tính điểm — map 1-1, xem WEIGHT_MAP.
 *
 * @property int    $id
 * @property int    $evaluation_template_id
 * @property int    $evaluation_criteria_id
 * @property string $weight_label     'khong_quan_trong'|'quan_trong'|'kha_quan_trong'|'rat_quan_trong'
 * @property int    $weight_value
 * @property int|null $required_score
 * @property bool   $count_in_total
 * @property int    $sort_order
 */
class EvaluationTemplateCriterion extends Model
{
    protected $table = 'evaluation_template_criteria';

    /** Map weight_label → weight_value mặc định (dùng khi tạo/sửa từ Service). */
    public const WEIGHT_MAP = [
        'khong_quan_trong' => 1,
        'quan_trong'       => 2,
        'kha_quan_trong'   => 3,
        'rat_quan_trong'   => 4,
    ];

    /** Nhãn hiển thị tiếng Việt phổ thông cho từng weight_label — dùng ở present(). */
    public const WEIGHT_LABELS = [
        'khong_quan_trong' => 'Không quan trọng',
        'quan_trong'       => 'Quan trọng',
        'kha_quan_trong'   => 'Khá quan trọng',
        'rat_quan_trong'   => 'Rất quan trọng',
    ];

    protected $fillable = [
        'evaluation_template_id',
        'evaluation_criteria_id',
        'weight_label',
        'weight_value',
        'required_score',
        'count_in_total',
        'sort_order',
    ];

    protected $casts = [
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
