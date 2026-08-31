<?php

namespace Modules\Evaluation\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\App\Models\Department;

/**
 * Bản chụp bất biến của cấu hình đánh giá một phòng ban tại thời điểm chốt.
 *
 * Mỗi phòng ban tại một thời điểm có tối đa 1 phiên bản `active`; chốt phiên
 * bản mới sẽ đẩy phiên bản cũ sang `superseded` (xử lý ở Service, không phải
 * ràng buộc DB). Báo cáo đã lưu luôn trỏ tới đúng phiên bản dùng lúc tạo nên
 * điểm cũ không đổi khi phòng ban sửa lại khung chấm điểm sau này.
 *
 * @property int         $id
 * @property int         $department_id
 * @property int         $version_no
 * @property string      $status  active | superseded
 * @property array|null  $kit_snapshot       payload EvaluationScoreKitService::present()
 * @property array|null  $criteria_snapshot  toàn bộ tiêu chí đang áp dụng lúc chốt
 * @property string|null $notes
 * @property int|null    $published_by
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $effective_from
 */
class EvaluationConfigVersion extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $table = 'evaluation_config_versions';

    protected $fillable = [
        'department_id',
        'version_no',
        'status',
        'kit_snapshot',
        'criteria_snapshot',
        'notes',
        'published_by',
        'published_at',
        'effective_from',
    ];

    protected $casts = [
        'kit_snapshot' => 'array',
        'criteria_snapshot' => 'array',
        'published_at' => 'datetime',
        'effective_from' => 'date',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * Tiêu chí kiểu hành vi (cộng / trừ điểm) trong bản chụp — nguồn để ghi
     * nhận sự kiện đánh giá và để báo cáo hiện phần điểm cộng / điểm trừ.
     *
     * @return list<array<string, mixed>>
     */
    public function behaviorCriteria(): array
    {
        $rows = [];
        foreach ($this->criteria_snapshot ?? [] as $criterion) {
            if (is_array($criterion) && ($criterion['type'] ?? null) === 'behavior') {
                $rows[] = $criterion;
            }
        }

        return $rows;
    }

    /**
     * Thang xếp loại dùng để quy điểm cuối ra mức — cách 1 dùng thang xếp loại
     * theo điểm, cách 2 dùng thang hiệu suất theo %.
     *
     * @return list<array{code: string, label: string, score: float}>
     */
    public function classificationLevels(): array
    {
        $kit = $this->kit_snapshot ?? [];
        $mode = $kit['mode'] ?? null;
        $levels = $mode === EvaluationScoreKit::MODE_WEIGHTED_TASK
            ? ($kit['performance_levels'] ?? [])
            : ($kit['base_adjust_levels'] ?? []);

        $out = [];
        foreach (is_array($levels) ? $levels : [] as $level) {
            if (! is_array($level)) {
                continue;
            }
            $out[] = [
                'code' => (string) ($level['code'] ?? ''),
                'label' => (string) ($level['label'] ?? ''),
                'score' => (float) ($level['score'] ?? 0),
            ];
        }

        return $out;
    }
}
