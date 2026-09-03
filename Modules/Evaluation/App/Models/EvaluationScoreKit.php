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
 * @property int $kit_schema_version
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

    /** Snapshot / kit cũ — giữ công thức legacy để báo cáo đã lưu không đổi số. */
    public const KIT_SCHEMA_VERSION_LEGACY = 1;

    /** Công thức Cách 2 hiện hành: việc chưa xong / thiếu dữ liệu = 0 điểm thực. */
    public const KIT_SCHEMA_VERSION = 2;

    public const CLASSIFICATION_LEVEL_MIN = 2;

    public const CLASSIFICATION_LEVEL_MAX = 12;

    public const SCALE_LEVEL_MAX = 20;

    public const DIFFICULTY_FACTOR_MIN = 0.01;

    public const DIFFICULTY_FACTOR_MAX = 10.0;

    public const PROGRESS_FACTOR_MIN = 0.5;

    public const PROGRESS_FACTOR_MAX = 1.1;

    public const QUALITY_FACTOR_MIN = 0.5;

    public const QUALITY_FACTOR_MAX = 1.0;

    public const PERFORMANCE_PERCENT_MAX = 200.0;

    protected $table = 'evaluation_score_kits';

    protected $fillable = [
        'department_id',
        'mode',
        'kit_schema_version',
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
        'kit_schema_version' => 'integer',
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
        $scores = array_map(
            static fn (array $row) => (float) ($row['score'] ?? 0),
            array_filter($rows, 'is_array'),
        );
        $looksLikeFactors = $scores !== []
            && max($scores) <= self::PROGRESS_FACTOR_MAX + 0.2
            && min($scores) >= self::PROGRESS_FACTOR_MIN - 0.2;

        // Chỉ vá thang hệ số 4 mức cũ (Đúng hạn → …), không pad tiêu chí ordinal 1–5.
        if ($looksLikeFactors
            && count($rows) === 4
            && ($firstCode === 'DH' || $firstLabel === 'Đúng hạn')
        ) {
            $rows = array_merge(array_slice(self::defaultProgressLevels(), 0, 2), $rows);
        }

        $normalized = self::normalizeLevels($rows, self::defaultProgressLevels());

        return self::sortLevelsBestFirst($normalized);
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

    /**
     * Snapshot/kit đang dùng công thức Cách 2 v2?
     *
     * @param  array<string, mixed>|null  $kit
     */
    public static function isSchemaV2(?array $kit): bool
    {
        $version = (int) ($kit['kit_schema_version'] ?? self::KIT_SCHEMA_VERSION_LEGACY);

        return $version >= self::KIT_SCHEMA_VERSION;
    }

    /**
     * Điểm mức tiêu chí (thường 1–5) → hệ số nhân theo hạng.
     *
     * Không scale thô theo giá trị tuyệt đối: thang ordinal 0–5 và 1–5 phải
     * cùng ra dải hệ số an toàn. Đã nằm trong dải đích thì giữ nguyên (idempotent).
     *
     * @param  list<array<string, mixed>>|null  $levels
     * @param  'progress'|'quality'|'difficulty'  $kind
     * @return list<array{code: string, label: string, score: float}>
     */
    public static function convertCriterionLevelsToFactors(?array $levels, string $kind): array
    {
        [$min, $max, $fallback] = match ($kind) {
            'quality' => [
                self::QUALITY_FACTOR_MIN,
                self::QUALITY_FACTOR_MAX,
                self::defaultQualityLevels(),
            ],
            'difficulty' => [
                self::DIFFICULTY_FACTOR_MIN,
                self::DIFFICULTY_FACTOR_MAX,
                self::defaultWeightedTaskLevels(),
            ],
            default => [
                self::PROGRESS_FACTOR_MIN,
                self::PROGRESS_FACTOR_MAX,
                self::defaultProgressLevels(),
            ],
        };

        $normalized = self::normalizeLevels($levels, $fallback);

        // Độ khó là trọng số khối lượng: giữ điểm dương của phòng (×1…×5).
        if ($kind === 'difficulty') {
            return array_map(static function (array $row): array {
                $score = (float) ($row['score'] ?? 1);
                $score = max(self::DIFFICULTY_FACTOR_MIN, min(self::DIFFICULTY_FACTOR_MAX, $score));

                return [
                    'code' => (string) ($row['code'] ?? ''),
                    'label' => (string) ($row['label'] ?? ''),
                    'score' => round($score, 2),
                ];
            }, $normalized);
        }

        if (self::levelsAlreadyInBand($normalized, $min, $max)) {
            return self::sortLevelsBestFirst($normalized);
        }

        return self::mapLevelsByRank($normalized, $min, $max, bestFirst: true);
    }

    /**
     * Điểm mức tiêu chí → ngưỡng phần trăm xếp loại (Cách 2) / điểm (Cách 1).
     *
     * @param  list<array<string, mixed>>|null  $levels
     * @return list<array{code: string, label: string, score: float, sort_order?: int}>
     */
    public static function convertCriterionLevelsToPercent(?array $levels, bool $withSort = false): array
    {
        $fallback = $withSort
            ? self::defaultBaseAdjustLevels()
            : self::defaultPerformanceLevels();
        $rows = is_array($levels) ? array_values($levels) : [];
        if ($rows === []) {
            return $fallback;
        }

        $prepared = [];
        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $prepared[] = [
                'code' => mb_substr(trim((string) ($row['code'] ?? '')), 0, 8),
                'label' => mb_substr(trim((string) ($row['label'] ?? '')), 0, 80) ?: ('Mức '.($index + 1)),
                'score' => (float) ($row['score'] ?? 0),
                'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : $index,
            ];
        }

        if ($prepared === []) {
            return $fallback;
        }

        $scores = array_map(static fn (array $row) => (float) $row['score'], $prepared);
        $maxScore = max($scores);
        // Đã là thang % / điểm tuyệt đối quanh 70–110 thì giữ.
        if ($maxScore > 20 || self::levelsAlreadyInBand($prepared, 0, self::PERFORMANCE_PERCENT_MAX) && $maxScore >= 70) {
            $out = self::sortLevelsBestFirst($prepared);
            if ($withSort) {
                foreach ($out as $index => $row) {
                    $out[$index]['sort_order'] = $index;
                }
            } else {
                $out = array_map(static fn (array $row) => [
                    'code' => $row['code'],
                    'label' => $row['label'],
                    'score' => round((float) $row['score'], 2),
                ], $out);
            }

            return $out;
        }

        $n = count($prepared);
        // Hạng theo điểm tiêu chí tăng dần: điểm thấp = tệ.
        usort($prepared, static function (array $a, array $b): int {
            $cmp = $a['score'] <=> $b['score'];

            return $cmp !== 0 ? $cmp : $a['sort_order'] <=> $b['sort_order'];
        });

        $anchors = [110.0, 100.0, 90.0, 80.0, 70.0];
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $row = $prepared[$i];
            if ($i === 0) {
                $percent = 0.0;
            } elseif ($n === 2) {
                $percent = 100.0;
            } else {
                // Các mức từ kém → tốt: bỏ mức sàn 0, nội suy 70…110.
                $rankFromWorst = $i; // 1 … n-1
                $topSlots = $n - 1;
                $t = ($rankFromWorst - 1) / max(1, $topSlots - 1);
                $percent = 70.0 + (110.0 - 70.0) * $t;
                if ($topSlots <= count($anchors)) {
                    // Ưu tiên neo mặc định khi số mức khớp.
                    $anchorIndex = $topSlots - $rankFromWorst;
                    if (isset($anchors[$anchorIndex]) && $topSlots === 5) {
                        $percent = $anchors[$anchorIndex];
                    }
                }
            }

            $item = [
                'code' => $row['code'],
                'label' => $row['label'],
                'score' => round($percent, 2),
            ];
            if ($withSort) {
                $item['sort_order'] = 0;
            }
            $out[] = $item;
        }

        // Xuất tốt → xấu (điểm % giảm dần).
        usort($out, static fn (array $a, array $b) => $b['score'] <=> $a['score']);
        if ($withSort) {
            foreach ($out as $index => $row) {
                $out[$index]['sort_order'] = $index;
            }
        }

        return $out;
    }

    /**
     * @param  list<array{code: string, label: string, score: float}>  $levels
     * @return list<array{max_variance_days: int|null, factor: float}>
     */
    public static function buildProgressBands(array $levels): array
    {
        $factors = [];
        foreach ($levels as $level) {
            if (is_array($level)) {
                $factors[] = (float) ($level['score'] ?? 1);
            }
        }
        $n = count($factors);
        if ($n === 0) {
            return [];
        }
        if ($n === 1) {
            return [['max_variance_days' => null, 'factor' => $factors[0]]];
        }

        $lateCount = (int) floor($n / 2);
        $earlyCount = max(0, $n - $lateCount - 1);
        $onTimeIndex = $earlyCount;
        $bands = [];

        for ($i = 0; $i < $earlyCount; $i++) {
            $daysEarly = $earlyCount - $i;
            $bands[] = [
                'max_variance_days' => -$daysEarly,
                'factor' => $factors[$i],
            ];
        }

        $bands[] = [
            'max_variance_days' => 0,
            'factor' => $factors[$onTimeIndex],
        ];

        $lateFactors = array_slice($factors, $onTimeIndex + 1);
        $lateCaps = self::lateDayCaps(count($lateFactors));
        foreach ($lateFactors as $i => $factor) {
            $bands[] = [
                'max_variance_days' => $lateCaps[$i],
                'factor' => $factor,
            ];
        }

        return $bands;
    }

    /**
     * Một hệ số / mức theo đúng thứ tự cấu hình (không nhân đôi code+label).
     *
     * @param  list<array<string, mixed>>|null  $levels
     * @return list<float>
     */
    public static function orderedFactors(?array $levels): array
    {
        $out = [];
        foreach (is_array($levels) ? $levels : [] as $level) {
            if (! is_array($level)) {
                continue;
            }
            $out[] = (float) ($level['score'] ?? 1);
        }

        return $out;
    }

    /**
     * @param  list<array{code: string, label: string, score: float}>  $levels
     */
    public static function levelsAlreadyInBand(array $levels, float $min, float $max): bool
    {
        if ($levels === []) {
            return false;
        }

        foreach ($levels as $level) {
            $score = (float) ($level['score'] ?? 0);
            if ($score < $min - 0.001 || $score > $max + 0.001) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array{code: string, label: string, score: float}>  $levels
     * @return list<array{code: string, label: string, score: float}>
     */
    private static function mapLevelsByRank(array $levels, float $min, float $max, bool $bestFirst): array
    {
        $n = count($levels);
        if ($n === 0) {
            return [];
        }

        $indexed = [];
        foreach ($levels as $index => $row) {
            $indexed[] = [
                'code' => (string) ($row['code'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'score' => (float) ($row['score'] ?? 0),
                'order' => $index,
            ];
        }

        usort($indexed, static function (array $a, array $b): int {
            $cmp = $a['score'] <=> $b['score'];

            return $cmp !== 0 ? $cmp : $a['order'] <=> $b['order'];
        });

        $out = [];
        foreach ($indexed as $rank => $row) {
            $t = $n === 1 ? 0.5 : $rank / ($n - 1);
            $factor = $min + ($max - $min) * $t;
            $out[] = [
                'code' => $row['code'],
                'label' => $row['label'],
                'score' => round($factor, 2),
                '_rank' => $rank,
            ];
        }

        if ($bestFirst) {
            usort($out, static fn (array $a, array $b) => $b['score'] <=> $a['score']);
        }

        return array_map(static fn (array $row) => [
            'code' => $row['code'],
            'label' => $row['label'],
            'score' => $row['score'],
        ], $out);
    }

    /**
     * @param  list<array{code: string, label: string, score: float}>  $levels
     * @return list<array{code: string, label: string, score: float}>
     */
    private static function sortLevelsBestFirst(array $levels): array
    {
        $out = array_values($levels);
        usort($out, static fn (array $a, array $b) => ((float) $b['score']) <=> ((float) $a['score']));

        return array_map(static fn (array $row) => [
            'code' => (string) ($row['code'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'score' => round((float) ($row['score'] ?? 0), 2),
        ], $out);
    }

    /** @return list<int|null> */
    private static function lateDayCaps(int $count): array
    {
        if ($count <= 0) {
            return [];
        }
        if ($count === 1) {
            return [null];
        }
        if ($count === 2) {
            return [2, null];
        }
        if ($count === 3) {
            return [2, 5, null];
        }

        $caps = [];
        for ($i = 0; $i < $count - 1; $i++) {
            $caps[] = $i + 1;
        }
        $caps[] = null;

        return $caps;
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
