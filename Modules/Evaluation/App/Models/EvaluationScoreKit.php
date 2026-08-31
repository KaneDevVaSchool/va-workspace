<?php

namespace Modules\Evaluation\App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\App\Models\Department;

/**
 * Cấu hình cách tính điểm của một phòng ban (1 dòng / phòng).
 *
 * @property int $id
 * @property int $department_id
 * @property string|null $mode Scoring method: base_adjust (cách 1 đếm số việc) | weighted_task (cách 2 hiệu suất)
 * @property float $base_score
 * @property float $task_base_score
 * @property float $quality_bonus_percent
 * @property float $points_per_completed_task
 * @property float $points_per_incomplete_task
 * @property bool $use_project_importance
 * @property int|null $classification_criterion_id
 * @property int|null $difficulty_criterion_id
 * @property int|null $progress_criterion_id
 * @property int|null $quality_criterion_id
 * @property bool $classification_use_default
 * @property bool $difficulty_use_default
 * @property bool $progress_use_default
 * @property bool $quality_use_default
 * @property list<array{code: string, label: string, score: float, sort_order: int}>|null $base_adjust_levels
 * @property list<array{code: string, label: string, score: float}>|null $weighted_task_levels
 * @property list<array{code: string, label: string, score: float}>|null $progress_levels
 * @property list<array{code: string, label: string, score: float}>|null $quality_levels
 * @property list<array{code: string, label: string, score: float}>|null $performance_levels
 * @property array{base: string, done: string, undone: string, weight: string, project: string, progress: string, quality: string, contrib: string, lock_difficulty: string}|null $formula
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class EvaluationScoreKit extends Model
{
    public const MODE_BASE_ADJUST = 'base_adjust';

    public const MODE_WEIGHTED_TASK = 'weighted_task';

    public const MODES = [
        self::MODE_BASE_ADJUST,
        self::MODE_WEIGHTED_TASK,
    ];

    public const CLASSIFICATION_LEVEL_MIN = 2;

    public const CLASSIFICATION_LEVEL_MAX = 12;

    public const SCALE_LEVEL_MAX = 20;

    protected $table = 'evaluation_score_kits';

    protected $fillable = [
        'department_id',
        'mode',
        'base_score',
        'task_base_score',
        'quality_bonus_percent',
        'points_per_completed_task',
        'points_per_incomplete_task',
        'use_project_importance',
        'classification_criterion_id',
        'difficulty_criterion_id',
        'progress_criterion_id',
        'quality_criterion_id',
        'classification_use_default',
        'difficulty_use_default',
        'progress_use_default',
        'quality_use_default',
        'base_adjust_levels',
        'weighted_task_levels',
        'progress_levels',
        'quality_levels',
        'performance_levels',
        'formula',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'base_score' => 'decimal:2',
        'task_base_score' => 'decimal:2',
        'quality_bonus_percent' => 'decimal:2',
        'points_per_completed_task' => 'decimal:2',
        'points_per_incomplete_task' => 'decimal:2',
        'use_project_importance' => 'boolean',
        'classification_use_default' => 'boolean',
        'difficulty_use_default' => 'boolean',
        'progress_use_default' => 'boolean',
        'quality_use_default' => 'boolean',
        'base_adjust_levels' => 'array',
        'weighted_task_levels' => 'array',
        'progress_levels' => 'array',
        'quality_levels' => 'array',
        'performance_levels' => 'array',
        'formula' => 'array',
    ];

    /**
     * @return array{base: string, done: string, undone: string, weight: string, project: string, progress: string, quality: string, contrib: string, lock_difficulty: string}
     */
    public static function defaultFormula(): array
    {
        return [
            'base' => 'on',
            'done' => 'add',
            'undone' => 'add',
            'weight' => 'on',
            'project' => 'off',
            'progress' => 'on',
            'quality' => 'on',
            'contrib' => 'off',
            'lock_difficulty' => 'on',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $raw
     * @return array{base: string, done: string, undone: string, weight: string, project: string, progress: string, quality: string, contrib: string, lock_difficulty: string}
     */
    public static function normalizeFormula(?array $raw): array
    {
        $def = self::defaultFormula();
        $onOff = ['on', 'off'];
        $ops = ['add', 'sub', 'off'];
        $row = is_array($raw) ? $raw : [];

        return [
            'base' => in_array($row['base'] ?? '', $onOff, true) ? $row['base'] : $def['base'],
            'done' => in_array($row['done'] ?? '', $ops, true) ? $row['done'] : $def['done'],
            'undone' => in_array($row['undone'] ?? '', $ops, true) ? $row['undone'] : $def['undone'],
            'weight' => in_array($row['weight'] ?? '', $onOff, true) ? $row['weight'] : $def['weight'],
            'project' => in_array($row['project'] ?? '', $onOff, true) ? $row['project'] : $def['project'],
            'progress' => in_array($row['progress'] ?? '', $onOff, true) ? $row['progress'] : $def['progress'],
            'quality' => in_array($row['quality'] ?? '', $onOff, true) ? $row['quality'] : $def['quality'],
            'contrib' => in_array($row['contrib'] ?? '', $onOff, true) ? $row['contrib'] : $def['contrib'],
            'lock_difficulty' => in_array($row['lock_difficulty'] ?? '', $onOff, true) ? $row['lock_difficulty'] : $def['lock_difficulty'],
        ];
    }

    /**
     * Cách 1 — mặc định khi phòng chưa tự đặt thang.
     * Admin được sửa mã / tên / mốc / thứ tự; không khóa số mức.
     *
     * @return list<array{code: string, label: string, score: float, sort_order: int}>
     */
    public static function defaultBaseAdjustLevels(): array
    {
        return [
            ['code' => 'XS', 'label' => 'Xuất sắc', 'score' => 110.0, 'sort_order' => 0],
            ['code' => 'T', 'label' => 'Tốt', 'score' => 100.0, 'sort_order' => 1],
            ['code' => 'K', 'label' => 'Khá', 'score' => 90.0, 'sort_order' => 2],
            ['code' => 'D', 'label' => 'Đạt', 'score' => 80.0, 'sort_order' => 3],
            ['code' => 'CD', 'label' => 'Chưa đạt', 'score' => 0.0, 'sort_order' => 4],
        ];
    }

    /**
     * Thang xếp loại cách 1 — số mức do phòng quyết (2–12).
     * Mỗi mức: code + name + min_score + sort_order. Không pad/cắt theo mặc định.
     *
     * @param  list<array<string, mixed>>|null  $levels
     * @return list<array{code: string, label: string, score: float, sort_order: int}>
     */
    public static function normalizeClassificationLevels(?array $levels): array
    {
        $rows = is_array($levels) ? array_values($levels) : [];
        $out = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $code = mb_substr(trim((string) ($row['code'] ?? '')), 0, 8);
            $label = mb_substr(trim((string) ($row['label'] ?? '')), 0, 80);
            $score = isset($row['score']) ? (float) $row['score'] : null;
            $sort = isset($row['sort_order']) ? (int) $row['sort_order'] : $index;

            if ($code === '' && $label === '' && $score === null) {
                continue;
            }

            $out[] = [
                'code' => $code,
                'label' => $label !== '' ? $label : 'Mức '.($index + 1),
                'score' => $score !== null && is_finite($score)
                    ? round(max(0, $score), 2)
                    : 0.0,
                'sort_order' => $sort,
            ];
        }

        if (count($out) < self::CLASSIFICATION_LEVEL_MIN) {
            return self::defaultBaseAdjustLevels();
        }

        if (count($out) > self::CLASSIFICATION_LEVEL_MAX) {
            $out = array_slice($out, 0, self::CLASSIFICATION_LEVEL_MAX);
        }

        usort($out, static fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);

        foreach ($out as $index => $row) {
            $out[$index]['sort_order'] = $index;
        }

        return $out;
    }

    /**
     * Cách 2 — độ khó quyết định điểm chuẩn / trọng số khối lượng, không phải thưởng.
     *
     * @return list<array{code: string, label: string, score: float}>
     */
    public static function defaultWeightedTaskLevels(): array
    {
        return [
            ['code' => 'RK', 'label' => 'Rất khó', 'score' => 1.50],
            ['code' => 'KH', 'label' => 'Khó', 'score' => 1.20],
            ['code' => 'TB', 'label' => 'Trung bình', 'score' => 1.00],
            ['code' => 'DE', 'label' => 'Dễ', 'score' => 0.85],
        ];
    }

    /**
     * @return list<array{code: string, label: string, score: float}>
     */
    public static function defaultProgressLevels(): array
    {
        return [
            ['code' => 'S20', 'label' => 'Sớm ≥20%', 'score' => 1.10],
            ['code' => 'S5', 'label' => 'Sớm dưới 20%', 'score' => 1.05],
            ['code' => 'DH', 'label' => 'Đúng hạn', 'score' => 1.00],
            ['code' => 'T2', 'label' => 'Trễ 1–2 ngày', 'score' => 0.90],
            ['code' => 'T5', 'label' => 'Trễ 3–5 ngày', 'score' => 0.75],
            ['code' => 'T6', 'label' => 'Trễ hơn 5 ngày', 'score' => 0.50],
        ];
    }

    /**
     * Thang 4 mức cũ (không có “sớm”) → chèn 2 mức sớm lên đầu.
     *
     * @param  list<array<string, mixed>>|null  $levels
     * @return list<array{code: string, label: string, score: float}>
     */
    public static function normalizeProgressLevels(?array $levels): array
    {
        $rows = is_array($levels) ? array_values($levels) : [];
        $firstCode = (string) ($rows[0]['code'] ?? '');
        $firstLabel = (string) ($rows[0]['label'] ?? '');
        if (count($rows) === 4 && ($firstCode === 'DH' || $firstLabel === 'Đúng hạn')) {
            $rows = array_merge(array_slice(self::defaultProgressLevels(), 0, 2), $rows);
        }

        return self::normalizeLevels($rows, self::defaultProgressLevels());
    }

    /**
     * @return list<array{code: string, label: string, score: float}>
     */
    public static function defaultQualityLevels(): array
    {
        return [
            ['code' => 'XS', 'label' => 'Xuất sắc', 'score' => 1.00],
            ['code' => 'DAT', 'label' => 'Đạt', 'score' => 1.00],
            ['code' => 'CS', 'label' => 'Cần sửa', 'score' => 0.80],
            ['code' => 'KD', 'label' => 'Không đạt', 'score' => 0.50],
        ];
    }

    /**
     * Cách 2 — xếp loại hiệu suất (sprint / dự án / nhân sự) theo %.
     *
     * @return list<array{code: string, label: string, score: float}>
     */
    public static function defaultPerformanceLevels(): array
    {
        return [
            ['code' => 'VTK', 'label' => 'Vượt kỳ vọng', 'score' => 110.0],
            ['code' => 'XS', 'label' => 'Xuất sắc', 'score' => 100.0],
            ['code' => 'T', 'label' => 'Tốt', 'score' => 90.0],
            ['code' => 'D', 'label' => 'Đạt', 'score' => 80.0],
            ['code' => 'CC', 'label' => 'Cần cải thiện', 'score' => 70.0],
            ['code' => 'KD', 'label' => 'Không đạt', 'score' => 0.0],
        ];
    }

    /**
     * @param  list<array<string, mixed>>|null  $levels
     * @param  list<array{code: string, label: string, score: float}>  $fallback
     * @return list<array{code: string, label: string, score: float}>
     */
    public static function normalizeLevels(?array $levels, array $fallback): array
    {
        $rows = is_array($levels) ? array_values($levels) : [];
        if ($rows === []) {
            $rows = $fallback;
        }

        $out = [];
        foreach (array_slice($rows, 0, self::SCALE_LEVEL_MAX) as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $default = $fallback[$i] ?? [
                'code' => '',
                'label' => 'Mức '.($i + 1),
                'score' => 1.0,
            ];
            $code = mb_substr(trim((string) ($row['code'] ?? '')), 0, 8);
            $label = mb_substr(trim((string) ($row['label'] ?? '')), 0, 80);
            $score = isset($row['score']) ? (float) $row['score'] : null;

            $out[] = [
                'code' => $code !== '' ? $code : $default['code'],
                'label' => $label !== '' ? $label : $default['label'],
                'score' => $score !== null && is_finite($score)
                    ? round($score, 2)
                    : $default['score'],
            ];
        }

        return $out !== [] ? $out : $fallback;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function classificationCriterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriteria::class, 'classification_criterion_id');
    }

    public function difficultyCriterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriteria::class, 'difficulty_criterion_id');
    }

    public function progressCriterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriteria::class, 'progress_criterion_id');
    }

    public function qualityCriterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriteria::class, 'quality_criterion_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
