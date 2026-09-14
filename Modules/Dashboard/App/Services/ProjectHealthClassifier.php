<?php

namespace Modules\Dashboard\App\Services;

use Illuminate\Support\Carbon;

/**
 * Phân loại "sức khoẻ" 1 dự án thành 3 nhóm — hoàn toàn từ dữ liệu đã có,
 * không cần bảng mới / AI (xem plan mục 4):
 *
 * - risk (Nguy cơ): trễ >7 ngày, HOẶC on_hold quá 14 ngày, HOẶC đã qua
 *   start_date mà chưa có task lá nào.
 * - warning (Cần chú ý): trễ 1–7 ngày, HOẶC tiến độ thấp hơn đáng kể so với
 *   % thời gian đã trôi qua (chênh > 25 điểm phần trăm).
 * - good (Tốt): còn lại — kể cả khi thiếu dữ liệu để đánh giá (không suy
 *   diễn xấu khi thiếu dữ liệu).
 */
class ProjectHealthClassifier
{
    public const RISK = 'risk';

    public const WARNING = 'warning';

    public const GOOD = 'good';

    /**
     * @param  object  $project  cần start_date, end_date, status, updated_at
     * @param  int  $taskCount  số task lá hợp lệ của project
     * @param  float|null  $progressPercent  tiến độ roll-up, null nếu chưa có dữ liệu
     */
    public function classify(object $project, int $taskCount, ?float $progressPercent): string
    {
        $today = Carbon::today();
        $isClosed = in_array($project->status, ['completed', 'cancelled'], true);

        if ($isClosed) {
            return self::GOOD;
        }

        $daysOverdue = $this->daysOverdue($project, $today);

        if ($daysOverdue > 7) {
            return self::RISK;
        }

        if ($project->status === 'on_hold' && ! empty($project->updated_at)) {
            $heldDays = Carbon::parse($project->updated_at)->diffInDays($today);
            if ($heldDays > 14) {
                return self::RISK;
            }
        }

        if ($taskCount === 0 && ! empty($project->start_date) && Carbon::parse($project->start_date)->lte($today)) {
            return self::RISK;
        }

        if ($daysOverdue >= 1) {
            return self::WARNING;
        }

        if ($progressPercent !== null && ! empty($project->start_date) && ! empty($project->end_date)) {
            $start = Carbon::parse($project->start_date);
            $end = Carbon::parse($project->end_date);
            $totalDays = max(1, $start->diffInDays($end));
            $elapsedDays = min($totalDays, max(0, $start->diffInDays($today)));
            $elapsedRatio = ($elapsedDays / $totalDays) * 100;

            if (($elapsedRatio - $progressPercent) > 25) {
                return self::WARNING;
            }
        }

        return self::GOOD;
    }

    public function daysOverdue(object $project, ?Carbon $today = null): int
    {
        $today ??= Carbon::today();

        if (empty($project->end_date) || in_array($project->status, ['completed', 'cancelled'], true)) {
            return 0;
        }

        $end = Carbon::parse($project->end_date);
        if ($end->gte($today)) {
            return 0;
        }

        return $end->diffInDays($today);
    }

    /** Nhóm số ngày trễ vào 1 trong 4 mốc — dùng cho Overdue Aging / Work Aging. */
    public function agingBucket(int $days): string
    {
        return match (true) {
            $days <= 3 => '0_3',
            $days <= 7 => '4_7',
            $days <= 14 => '8_14',
            default => 'over_14',
        };
    }
}
