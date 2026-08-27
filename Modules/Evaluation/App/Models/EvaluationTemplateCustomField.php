<?php

namespace Modules\Evaluation\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Trường tùy biến" trên Mẫu đánh giá — chỉ lưu định nghĩa field (PR5),
 * chưa có UI nhập giá trị thật (chờ phiếu đánh giá Giai đoạn D).
 *
 * @property int         $id
 * @property int         $evaluation_template_id
 * @property string      $label
 * @property string      $field_type   'text'|'bonus'
 * @property array|null  $options      không dùng với 2 loại hiện tại; giữ cột cho dữ liệu cũ
 * @property bool        $is_required
 * @property int         $sort_order
 */
class EvaluationTemplateCustomField extends Model
{
    protected $table = 'evaluation_template_custom_fields';

    public const FIELD_TYPES = ['text', 'bonus'];

    public const TYPE_LABELS = [
        'text'  => 'Chữ',
        'bonus' => 'Điểm phụ thêm',
    ];

    protected $fillable = [
        'evaluation_template_id',
        'label',
        'field_type',
        'options',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'options'     => 'array',
        'is_required' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'evaluation_template_id');
    }
}
