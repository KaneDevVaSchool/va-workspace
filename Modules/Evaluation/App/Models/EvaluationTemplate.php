<?php

namespace Modules\Evaluation\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Identity\App\Models\Department;

/**
 * Mẫu đánh giá (Evaluation Giai đoạn C) — gộp nhiều EvaluationCriteria
 * thành 1 bộ có trọng số. Xem plans/2026-08-26-mau-danh-gia.md.
 *
 * @property int         $id
 * @property int|null    $department_id     phòng ban tạo ra mẫu; null = mẫu chung do superadmin tạo
 * @property string      $code              mã tự sinh, ví dụ EVT-0001
 * @property string      $name
 * @property string|null $description
 * @property bool        $is_global         dùng chung cho toàn bộ hệ thống
 * @property bool        $is_active
 * @property int|null    $created_by
 * @property int|null    $updated_by
 */
class EvaluationTemplate extends Model
{
    protected $table = 'evaluation_templates';

    public const WITH_PRESENT = [
        'department',
        'creator.department',
        'updater.department',
        'templateCriteria.criterion.criterionType',
        'templateCriteria.criterion.department',
        'positions',
        'customFields',
    ];

    protected $fillable = [
        'department_id',
        'code',
        'name',
        'description',
        'is_global',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_global' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** N-N thật với evaluation_criteria, đi qua pivot model để giữ trọng số/required_score/count_in_total. */
    public function templateCriteria(): HasMany
    {
        return $this->hasMany(EvaluationTemplateCriterion::class, 'evaluation_template_id')
            ->orderBy('sort_order');
    }

    /** Tổng điểm tối đa có thể đạt, cộng từ maxScore từng tiêu chí có count_in_total = true. */
    public function getMaxScoreAttribute(): float
    {
        return (float) $this->templateCriteria
            ->where('count_in_total', true)
            ->sum(fn (EvaluationTemplateCriterion $tc) => $tc->criterion?->max_score ?? 0.0);
    }

    /** N-N với "Vị trí đánh giá" — danh mục chức danh dùng chung toàn hệ thống. */
    public function positions(): BelongsToMany
    {
        return $this->belongsToMany(
            EvaluationPosition::class,
            'evaluation_template_positions',
            'evaluation_template_id',
            'evaluation_position_id',
        );
    }

    /** "Trường tùy biến" — chỉ định nghĩa field (PR5), chưa có giá trị thật. */
    public function customFields(): HasMany
    {
        return $this->hasMany(EvaluationTemplateCustomField::class, 'evaluation_template_id')
            ->orderBy('sort_order');
    }
}
