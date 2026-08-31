<?php

namespace Modules\Evaluation\App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Evaluation\App\Models\EvaluationConfigVersion;
use Modules\Evaluation\App\Models\EvaluationEvent;
use Modules\Evaluation\App\Models\EvaluationScoreKit;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationEventRepositoryInterface;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Repositories\Contracts\TaskRepositoryInterface;

/**
 * Tính điểm đánh giá của nhân sự trong một kỳ, theo đúng một phiên bản cấu
 * hình đã chốt.
 *
 * Điểm cuối gộp hai nguồn:
 *   - Công việc: chạy công thức khung chấm điểm của phòng ban trên các task
 *     thuộc kỳ (cách 1 đếm số việc, cách 2 hiệu suất theo độ khó / tiến độ /
 *     chất lượng).
 *   - Hành vi: cộng / trừ các sự kiện đánh giá đã duyệt phát sinh trong kỳ.
 *
 *   điểm cuối = điểm khởi đầu + điều chỉnh từ công việc + điểm cộng - điểm trừ
 *
 * Toàn bộ tham số lấy từ bản chụp trong phiên bản, không đọc cấu hình sống —
 * nhờ vậy báo cáo cũ giữ nguyên kết quả khi phòng ban sửa khung chấm điểm.
 */
class EvaluationScoreComputeService
{
    public function __construct(
        private readonly TaskRepositoryInterface $tasks,
        private readonly EvaluationEventRepositoryInterface $events,
    ) {}

    /**
     * Tính cho nhiều nhân sự cùng lúc (một lượt truy vấn cho cả phòng ban).
     *
     * $asOf là mốc thời gian dùng để xét một việc chưa xong đã trễ hạn chưa.
     * Mặc định lấy ngày cuối kỳ — xem chú thích ở progressFactor() để hiểu vì
     * sao không được lấy "hôm nay".
     *
     * @param  list<array{id: int, name: string}>  $people
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     summary: array<string, mixed>
     * }
     */
    public function computeForPeople(
        array $people,
        EvaluationConfigVersion $version,
        string $from,
        string $to,
        ?string $asOf = null,
    ): array {
        $asOf ??= $to;
        $userIds = array_values(array_map(static fn (array $p) => (int) $p['id'], $people));

        $tasksByUser = $userIds === []
            ? collect()
            : $this->tasks
                ->forEvaluationPeriod($userIds, $from, $to, (int) $version->department_id)
                ->groupBy('assignee_id');

        $eventsByUser = $userIds === []
            ? collect()
            : $this->events->approvedForDepartmentInPeriod(
                (int) $version->department_id,
                $userIds,
                $from,
                $to,
            );

        $rows = [];
        foreach ($people as $person) {
            $userId = (int) $person['id'];
            $rows[] = $this->buildRow(
                $userId,
                (string) $person['name'],
                $version,
                $tasksByUser->get($userId, collect()),
                $eventsByUser->get($userId, collect()),
                $asOf,
            );
        }

        return [
            'rows' => $rows,
            'summary' => $this->summarize($rows, $version),
        ];
    }

    /**
     * Tính cho đúng một nhân sự — dùng cho màn hình chi tiết và cho việc tính
     * lại một dòng sau khi ghi nhận đánh giá.
     *
     * @return array<string, mixed>
     */
    public function computeForUser(
        int $userId,
        string $userName,
        EvaluationConfigVersion $version,
        string $from,
        string $to,
        ?string $asOf = null,
    ): array {
        $tasks = $this->tasks->forEvaluationPeriod(
            [$userId],
            $from,
            $to,
            (int) $version->department_id,
        );
        $events = $this->events->approvedForUserInPeriod(
            (int) $version->department_id,
            $userId,
            $from,
            $to,
        );

        return $this->buildRow($userId, $userName, $version, $tasks, $events, $asOf ?? $to);
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @param  Collection<int, EvaluationEvent>  $events
     * @return array<string, mixed>
     */
    private function buildRow(
        int $userId,
        string $userName,
        EvaluationConfigVersion $version,
        Collection $tasks,
        Collection $events,
        string $asOf,
    ): array {
        $kit = $version->kit_snapshot ?? [];
        $mode = $kit['mode'] ?? EvaluationScoreKit::MODE_BASE_ADJUST;

        $taskResult = $mode === EvaluationScoreKit::MODE_WEIGHTED_TASK
            ? $this->computeWeightedTask($tasks, $kit, $asOf)
            : $this->computeBaseAdjust($tasks, $kit, $asOf);

        $bonus = 0.0;
        $penalty = 0.0;
        $eventBreakdown = [];
        foreach ($events as $event) {
            $score = (float) $event->score;
            if ($score >= 0) {
                $bonus += $score;
            } else {
                $penalty += abs($score);
            }

            $snapshot = $event->criterion_snapshot ?? [];
            $eventBreakdown[] = [
                'event_id' => $event->id,
                'criterion_id' => $event->criterion_id,
                'criterion_name' => $snapshot['name'] ?? $event->criterion?->name,
                'criterion_type_name' => $snapshot['criterion_type_name'] ?? null,
                'level_code' => $event->level_code,
                'level_label' => $event->level_label,
                'score' => $score,
                'occurred_at' => $event->occurred_at?->toDateString(),
                'reason' => $event->reason,
                'task_id' => $event->task_id ? (int) $event->task_id : null,
            ];
        }

        $startScore = $taskResult['start_score'];
        $finalScore = round($startScore + $taskResult['adjustment'] + $bonus - $penalty, 2);
        $classification = $this->classify($finalScore, $version);

        return [
            'user_id' => $userId,
            'user_name' => $userName,
            'start_score' => round($startScore, 2),
            'task_adjustment' => round($taskResult['adjustment'], 2),
            'bonus' => round($bonus, 2),
            'penalty' => round($penalty, 2),
            'final_score' => $finalScore,
            'classification_code' => $classification['code'],
            'classification_label' => $classification['label'],
            'task_count' => $tasks->count(),
            'event_count' => count($eventBreakdown),
            'missing' => $taskResult['missing'],
            'missing_total' => array_sum($taskResult['missing']),
            'task_status_counts' => $this->countTasks($taskResult['breakdown']),
            'criterion_totals' => $this->summarizeCriteria($eventBreakdown),
            'task_breakdown' => $taskResult['breakdown'],
            'event_breakdown' => $eventBreakdown,
        ];
    }

    /**
     * Đếm công việc cho bảng tổng hợp.
     *
     * Hai nhóm đếm ĐỘC LẬP với nhau, không loại trừ nhau: một việc hoàn thành
     * trễ hạn vừa cộng vào `by_status.completed` vừa cộng vào
     * `by_timeliness.overdue`. Nếu gộp làm một thì cột "Quá hạn" trên bảng sẽ
     * bị hiểu thành một trạng thái riêng, và việc hoàn thành muộn sẽ biến mất
     * khỏi cột "Hoàn thành".
     *
     * @param  list<array<string, mixed>>  $breakdown
     * @return array<string, mixed>
     */
    private function countTasks(array $breakdown): array
    {
        $byStatus = [
            'not_started' => 0,
            'in_progress' => 0,
            'on_hold' => 0,
            'completed' => 0,
        ];
        $byTimeliness = ['on_time' => 0, 'overdue' => 0, 'unknown' => 0];

        foreach ($breakdown as $task) {
            $status = (string) ($task['status'] ?? '');
            if (array_key_exists($status, $byStatus)) {
                $byStatus[$status]++;
            }

            $state = (string) ($task['on_time_state'] ?? 'unknown');
            if (array_key_exists($state, $byTimeliness)) {
                $byTimeliness[$state]++;
            }
        }

        return [
            'total' => count($breakdown),
            'by_status' => $byStatus,
            'by_timeliness' => $byTimeliness,
        ];
    }

    /**
     * Gộp các lần ghi nhận theo tiêu chí — mỗi tiêu chí là một nhóm cột trên
     * bảng tổng hợp, cần số lần và tổng điểm.
     *
     * @param  list<array<string, mixed>>  $eventBreakdown
     * @return list<array<string, mixed>>
     */
    private function summarizeCriteria(array $eventBreakdown): array
    {
        $totals = [];

        foreach ($eventBreakdown as $event) {
            $criterionId = (int) ($event['criterion_id'] ?? 0);
            if (! array_key_exists($criterionId, $totals)) {
                $totals[$criterionId] = [
                    'criterion_id' => $criterionId,
                    'criterion_name' => $event['criterion_name'] ?? null,
                    'count' => 0,
                    'score' => 0.0,
                ];
            }

            $totals[$criterionId]['count']++;
            $totals[$criterionId]['score'] += (float) ($event['score'] ?? 0);
        }

        return array_values(array_map(
            static function (array $total): array {
                $total['score'] = round($total['score'], 2);

                return $total;
            },
            $totals,
        ));
    }

    /**
     * Cách 1 — điểm khởi đầu cộng / trừ theo số việc hoàn thành và chưa hoàn
     * thành. Mọi việc tính như nhau, không xét độ khó.
     *
     * @param  Collection<int, Task>  $tasks
     * @param  array<string, mixed>  $kit
     * @return array{start_score: float, adjustment: float, breakdown: list<array<string, mixed>>, missing: array{difficulty: int, progress: int, quality: int}}
     */
    private function computeBaseAdjust(Collection $tasks, array $kit, string $asOf): array
    {
        $formula = EvaluationScoreKit::normalizeFormula($kit['formula'] ?? null);
        $startScore = $formula['base'] === 'on' ? (float) ($kit['base_score'] ?? 100) : 0.0;
        $perDone = (float) ($kit['points_per_completed_task'] ?? 0);
        $perUndone = (float) ($kit['points_per_incomplete_task'] ?? 0);

        $adjustment = 0.0;
        $breakdown = [];

        foreach ($tasks as $task) {
            $done = $task->status === 'completed';
            $operator = $done ? $formula['done'] : $formula['undone'];

            // Operator "off" nghĩa là loại việc này không cộng / trừ điểm —
            // nhưng việc vẫn tồn tại, vẫn phải hiện trên bảng tổng hợp và vẫn
            // được đếm. Bỏ qua hẳn thì tổng số việc trên bảng sẽ thiếu.
            $contribution = 0.0;
            if ($operator !== 'off') {
                $points = $done ? $perDone : $perUndone;
                $contribution = $operator === 'sub' ? -$points : $points;
                $adjustment += $contribution;
            }

            $breakdown[] = $this->taskEntry($task, $asOf, [
                'is_completed' => $done,
                'contribution' => round($contribution, 2),
            ]);
        }

        // Cách 1 chỉ đếm số việc hoàn thành / chưa hoàn thành, không dùng độ
        // khó hay chất lượng nên không có gì để thiếu.
        return [
            'start_score' => $startScore,
            'adjustment' => $adjustment,
            'breakdown' => $breakdown,
            'missing' => $this->emptyMissing(),
        ];
    }

    /**
     * Cách 2 — hiệu suất việc.
     *
     * Mỗi việc có điểm chuẩn (điểm cơ bản × độ khó) và điểm thực (điểm chuẩn ×
     * tiến độ × chất lượng). Hiệu suất = tổng thực / tổng chuẩn × 100, cộng
     * thêm phần thưởng chất lượng xuất sắc nếu có.
     *
     * Điểm khởi đầu ở cách này bằng 0: hiệu suất tự nó đã là điểm.
     *
     * @param  Collection<int, Task>  $tasks
     * @param  array<string, mixed>  $kit
     * @return array{start_score: float, adjustment: float, breakdown: list<array<string, mixed>>, missing: array{difficulty: int, progress: int, quality: int}}
     */
    private function computeWeightedTask(Collection $tasks, array $kit, string $asOf): array
    {
        $formula = EvaluationScoreKit::normalizeFormula($kit['formula'] ?? null);
        $taskBase = (float) ($kit['task_base_score'] ?? 100);
        $difficultyLevels = $this->difficultyMap($kit);
        $progressLevels = $this->levelMap($kit['progress_levels'] ?? []);
        $qualityLevels = $this->levelMap($kit['quality_levels'] ?? []);
        $qualityBonusPercent = (float) ($kit['quality_bonus_percent'] ?? 0);

        $totalStandard = 0.0;
        $totalActual = 0.0;
        $excellentCount = 0;
        $breakdown = [];
        $missing = $this->emptyMissing();

        foreach ($tasks as $task) {
            $difficulty = 1.0;
            if ($formula['weight'] === 'on') {
                $matched = $this->difficultyFactor($task, $difficultyLevels);
                if ($matched === null) {
                    $missing['difficulty']++;
                } else {
                    $difficulty = $matched;
                }
            }
            $standard = $taskBase * $difficulty;

            $progressFactor = 1.0;
            if ($formula['progress'] === 'on') {
                if ($task->end_date === null
                    || ($task->status === 'completed' && $task->actual_end_date === null)) {
                    $missing['progress']++;
                }
                $progressFactor = $this->progressFactor($task, $progressLevels, $asOf);
            }

            $qualityFactor = 1.0;
            if ($formula['quality'] === 'on') {
                $matched = $this->qualityFactor($task, $qualityLevels);
                if ($matched === null) {
                    $missing['quality']++;
                } else {
                    $qualityFactor = $matched;
                }
            }

            $actual = $standard * $progressFactor * $qualityFactor;

            if ($formula['quality'] === 'on' && $this->isExcellentQuality($task)) {
                $excellentCount++;
            }

            $totalStandard += $standard;
            $totalActual += $actual;

            $breakdown[] = $this->taskEntry($task, $asOf, [
                'is_completed' => $task->status === 'completed',
                'standard_score' => round($standard, 2),
                'actual_score' => round($actual, 2),
                'difficulty_factor' => round($difficulty, 2),
                'progress_factor' => round($progressFactor, 2),
                'quality_factor' => round($qualityFactor, 2),
                'contribution' => round($actual, 2),
            ]);
        }

        if ($totalStandard <= 0) {
            return [
                'start_score' => 0.0,
                'adjustment' => 0.0,
                'breakdown' => $breakdown,
                'missing' => $missing,
            ];
        }

        $performance = $totalActual / $totalStandard * 100;

        if ($excellentCount > 0 && $qualityBonusPercent > 0) {
            $performance += $qualityBonusPercent * ($excellentCount / max(1, $tasks->count()));
        }

        return [
            'start_score' => 0.0,
            'adjustment' => $performance,
            'breakdown' => $breakdown,
            'missing' => $missing,
        ];
    }

    /**
     * Hệ số độ khó của một việc — tra theo giá trị độ khó ghi trên công việc
     * (`task.priority`). Trả null khi không tra được mức nào: người gọi đếm
     * vào phần "thiếu dữ liệu" thay vì âm thầm coi như trung bình.
     *
     * @param  array<string, float>  $levels
     */
    private function difficultyFactor(Task $task, array $levels): ?float
    {
        if ($levels === []) {
            return null;
        }

        return $this->matchLevel((string) $task->priority, $levels);
    }

    /**
     * Bảng tra độ khó của bản chụp.
     *
     * Ưu tiên `difficulty_lookup` — bảng do EvaluationConfigVersionService
     * chụp sẵn, đã gộp mọi dạng viết của cùng một mức. Bản chụp cũ (chốt
     * trước khi có bảng này) rơi về thang độ khó như trước để báo cáo cũ vẫn
     * mở được.
     *
     * @param  array<string, mixed>  $kit
     * @return array<string, float>
     */
    private function difficultyMap(array $kit): array
    {
        $lookup = $kit['difficulty_lookup'] ?? null;
        if (is_array($lookup) && $lookup !== []) {
            $map = [];
            foreach ($lookup as $key => $score) {
                $map[(string) $key] = (float) $score;
            }

            return $map;
        }

        return $this->levelMap($kit['weighted_task_levels'] ?? []);
    }

    /** @return array{difficulty: int, progress: int, quality: int} */
    private function emptyMissing(): array
    {
        return ['difficulty' => 0, 'progress' => 0, 'quality' => 0];
    }

    /**
     * Một dòng công việc trong bảng chi tiết.
     *
     * Gộp phần chung của cả hai cách tính vào đây để hai nhánh không mô tả
     * công việc mỗi nơi một kiểu. Ngày hạn / ngày hoàn thành và trạng thái
     * đúng hạn được tính SẴN Ở ĐÂY để giao diện chỉ việc hiển thị — giao diện
     * tự so ngày sẽ sớm lệch với cách chấm điểm của máy chủ.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function taskEntry(Task $task, string $asOf, array $extra): array
    {
        $state = $this->taskTimeliness($task, $asOf);

        return array_merge([
            'task_id' => $task->id,
            'title' => $task->title,
            'status' => $task->status,
            'project_name' => $task->project?->name,
            'end_date' => $task->end_date?->toDateString(),
            'actual_end_date' => $task->actual_end_date?->toDateString(),
            'on_time_state' => $state,
            'is_overdue' => $state === 'overdue',
        ], $extra);
    }

    /**
     * Việc này đúng hạn hay trễ hạn.
     *
     * Việc đã hoàn thành so ngày hoàn thành thực tế với hạn. Việc chưa hoàn
     * thành so hạn với $asOf — mốc cuối kỳ đánh giá, KHÔNG phải hôm nay (xem
     * progressFactor()).
     *
     * Thiếu ngày để so thì trả "unknown": không đủ căn cứ nói đúng hạn hay
     * trễ, nên không được đếm vào cả hai bên.
     */
    private function taskTimeliness(Task $task, string $asOf): string
    {
        if ($task->end_date === null) {
            return 'unknown';
        }

        if ($task->status === 'completed') {
            if ($task->actual_end_date === null) {
                return 'unknown';
            }

            return $task->actual_end_date->gt($task->end_date) ? 'overdue' : 'on_time';
        }

        return $task->end_date->lt(Carbon::parse($asOf)->startOfDay()) ? 'overdue' : 'on_time';
    }

    /**
     * Hệ số tiến độ — so ngày hoàn thành thực tế với hạn.
     *
     * Việc chưa hoàn thành mà đã quá hạn nhận mức trễ nặng nhất; chưa tới hạn
     * thì tính như đúng hạn (chưa có cơ sở trừ điểm).
     *
     * Mốc so sánh là $asOf (ngày cuối kỳ), KHÔNG phải "hôm nay". Lấy hôm nay
     * thì một việc chưa xong sẽ tự chuyển thành trễ hạn khi thời gian trôi
     * qua, khiến báo cáo đã lưu của kỳ cũ mở lại ra điểm khác — đúng thứ mà
     * phiên bản cấu hình sinh ra để ngăn.
     *
     * @param  array<string, float>  $levels
     */
    private function progressFactor(Task $task, array $levels, string $asOf): float
    {
        if ($levels === []) {
            return 1.0;
        }

        $ordered = array_values($levels);
        $best = $ordered[0];
        $worst = $ordered[count($ordered) - 1];

        if ($task->end_date === null) {
            return $this->middleLevel($ordered);
        }

        if ($task->status !== 'completed') {
            return Carbon::parse($asOf)->startOfDay()->gt($task->end_date)
                ? $worst
                : $this->middleLevel($ordered);
        }

        if ($task->actual_end_date === null) {
            return $this->middleLevel($ordered);
        }

        $varianceDays = $task->end_date->diffInDays($task->actual_end_date, false);

        return $this->progressLevelForVariance($varianceDays, $ordered, $best, $worst);
    }

    /**
     * Thang tiến độ mặc định xếp từ sớm nhất tới trễ nhất, nên chọn mức theo
     * số ngày lệch: âm là sớm, 0 là đúng hạn, dương là trễ.
     *
     * @param  list<float>  $ordered
     */
    private function progressLevelForVariance(
        int $varianceDays,
        array $ordered,
        float $best,
        float $worst,
    ): float {
        $count = count($ordered);

        if ($varianceDays <= -1) {
            return $best;
        }

        if ($varianceDays <= 0) {
            return $this->middleLevel($ordered);
        }

        if ($count <= 2) {
            return $worst;
        }

        $lateLevels = array_slice($ordered, (int) ceil($count / 2));
        if ($lateLevels === []) {
            return $worst;
        }

        $index = min($varianceDays - 1, count($lateLevels) - 1);

        return $lateLevels[$index];
    }

    /** @param  list<float>  $ordered */
    private function middleLevel(array $ordered): float
    {
        $count = count($ordered);
        if ($count === 0) {
            return 1.0;
        }

        return $ordered[(int) floor(($count - 1) / 2)];
    }

    /**
     * Hệ số chất lượng — lấy theo kết quả chấm việc.
     *
     * Ô kết quả chấm là text nhập tay, người chấm gõ "cần sửa" hay "Cần Sửa"
     * đều phải ra cùng một mức, nên khớp bỏ dấu và bỏ hoa thường. Không khớp
     * mức nào (hoặc chưa chấm) thì trả null để đếm vào phần thiếu dữ liệu.
     *
     * @param  array<string, float>  $levels
     */
    private function qualityFactor(Task $task, array $levels): ?float
    {
        if ($levels === []) {
            return null;
        }

        $result = $task->taskScore?->rating_result;
        if ($result === null || trim((string) $result) === '') {
            return null;
        }

        return $this->matchLevel((string) $result, $levels);
    }

    private function isExcellentQuality(Task $task): bool
    {
        $result = mb_strtolower(trim((string) ($task->taskScore?->rating_result ?? '')));

        return $result !== '' && (str_contains($result, 'xuất sắc') || $result === 'xs');
    }

    /**
     * Khớp giá trị với mức trong thang — so cả mã và tên mức.
     *
     * Khớp hai vòng: đúng nguyên văn trước (chỉ bỏ hoa thường), không được
     * mới khớp lỏng — bỏ dấu tiếng Việt và gom khoảng trắng. Khớp lỏng để sau
     * chứ không gộp một vòng, vì hai mức khác nhau có thể trùng nhau sau khi
     * bỏ dấu; ưu tiên bản khớp chặt thì không chọn nhầm.
     *
     * @param  array<string, float>  $levels
     */
    private function matchLevel(string $value, array $levels): ?float
    {
        $needle = mb_strtolower(trim($value));
        if ($needle === '') {
            return null;
        }

        foreach ($levels as $key => $score) {
            if (mb_strtolower((string) $key) === $needle) {
                return $score;
            }
        }

        $loose = $this->looseKey($value);
        if ($loose === '') {
            return null;
        }

        foreach ($levels as $key => $score) {
            if ($this->looseKey((string) $key) === $loose) {
                return $score;
            }
        }

        return null;
    }

    /** Chuẩn hoá để khớp lỏng: bỏ dấu, bỏ hoa thường, gom khoảng trắng. */
    private function looseKey(string $value): string
    {
        $ascii = mb_strtolower(Str::ascii(trim($value)));

        return (string) preg_replace('/\s+/', ' ', $ascii);
    }

    /**
     * Thang mức thành map "mã / tên" => hệ số, giữ nguyên thứ tự đã cấu hình.
     *
     * @param  mixed  $levels
     * @return array<string, float>
     */
    private function levelMap(mixed $levels): array
    {
        $map = [];
        foreach (is_array($levels) ? $levels : [] as $level) {
            if (! is_array($level)) {
                continue;
            }
            $score = (float) ($level['score'] ?? 1);
            $code = trim((string) ($level['code'] ?? ''));
            $label = trim((string) ($level['label'] ?? ''));

            if ($code !== '') {
                $map[$code] = $score;
            }
            if ($label !== '' && ! array_key_exists($label, $map)) {
                $map[$label] = $score;
            }
        }

        return $map;
    }

    /**
     * Quy điểm cuối về mức xếp loại — chọn mức đầu tiên (từ cao xuống thấp) mà
     * điểm đạt tới.
     *
     * @return array{code: string|null, label: string|null}
     */
    private function classify(float $finalScore, EvaluationConfigVersion $version): array
    {
        $levels = $version->classificationLevels();
        if ($levels === []) {
            return ['code' => null, 'label' => null];
        }

        usort($levels, static fn (array $a, array $b) => $b['score'] <=> $a['score']);

        foreach ($levels as $level) {
            if ($finalScore >= $level['score']) {
                return ['code' => $level['code'], 'label' => $level['label']];
            }
        }

        $lowest = $levels[count($levels) - 1];

        return ['code' => $lowest['code'], 'label' => $lowest['label']];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function summarize(array $rows, EvaluationConfigVersion $version): array
    {
        $total = count($rows);
        $scores = array_map(static fn (array $row) => (float) $row['final_score'], $rows);

        $distribution = [];
        foreach ($version->classificationLevels() as $level) {
            $distribution[] = [
                'code' => $level['code'],
                'label' => $level['label'],
                'count' => count(array_filter(
                    $rows,
                    static fn (array $row) => $row['classification_code'] === $level['code'],
                )),
            ];
        }

        // Gộp phần thiếu dữ liệu của cả phòng ban để trang xem báo cáo cảnh báo
        // được — nếu không người xem tưởng mọi việc đều đã tính đủ.
        $missing = $this->emptyMissing();
        foreach ($rows as $row) {
            foreach ($this->emptyMissing() as $key => $_) {
                $missing[$key] += (int) ($row['missing'][$key] ?? 0);
            }
        }

        return [
            'total_people' => $total,
            'average_score' => $total > 0 ? round(array_sum($scores) / $total, 2) : 0.0,
            'highest_score' => $total > 0 ? round(max($scores), 2) : 0.0,
            'lowest_score' => $total > 0 ? round(min($scores), 2) : 0.0,
            'distribution' => $distribution,
            'missing' => $missing,
            'missing_total' => array_sum($missing),
        ];
    }
}
