<?php

namespace Modules\Report\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Evaluation\App\Models\EvaluationConfigVersion;
use Modules\Identity\App\Models\Department;

/**
 * Báo cáo đã cấu hình của một phòng ban.
 *
 * @property int         $id
 * @property int         $department_id
 * @property string      $report_type
 * @property string      $title
 * @property string      $period_type  month | quarter | custom
 * @property \Illuminate\Support\Carbon $period_from
 * @property \Illuminate\Support\Carbon $period_to
 * @property int|null    $evaluation_config_version_id
 * @property string      $status  draft | saved
 */
class Report extends Model
{
    public const TYPE_PERSONNEL_EVALUATION = 'personnel_evaluation';

    public const TYPES = [self::TYPE_PERSONNEL_EVALUATION];

    public const PERIOD_TYPES = ['month', 'quarter', 'custom'];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SAVED = 'saved';

    public const WITH_PRESENT = [
        'department',
        'evaluationConfigVersion',
        'viewers.user',
        'filters',
        'columns',
        'criteria',
        'peopleSnapshot',
        'creator',
        'updater',
    ];

    protected $table = 'reports';

    protected $fillable = [
        'department_id',
        'report_type',
        'title',
        'period_type',
        'period_from',
        'period_to',
        'evaluation_config_version_id',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function evaluationConfigVersion(): BelongsTo
    {
        return $this->belongsTo(EvaluationConfigVersion::class, 'evaluation_config_version_id');
    }

    public function viewers(): HasMany
    {
        return $this->hasMany(ReportViewer::class);
    }

    public function filters(): HasMany
    {
        return $this->hasMany(ReportFilter::class);
    }

    public function columns(): HasMany
    {
        return $this->hasMany(ReportColumn::class);
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(ReportCriterion::class);
    }

    public function peopleSnapshot(): HasMany
    {
        return $this->hasMany(ReportPersonSnapshot::class)->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Nhân sự được chọn để đưa vào báo cáo — rỗng nghĩa là toàn phòng ban.
     *
     * @return list<int>
     */
    public function filteredUserIds(): array
    {
        return $this->filters
            ->where('filter_key', 'user_id')
            ->map(static fn (ReportFilter $filter) => (int) $filter->filter_value)
            ->values()
            ->all();
    }
}
