<?php

namespace Modules\Dashboard\App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tính % tiến độ roll-up của Project từ Task lá — bảng `projects` KHÔNG có
 * cột tiến độ riêng (xem Modules/Project/App/Services/ProjectService.php,
 * progress_percent luôn null ở giai đoạn 1). Dashboard tự tổng hợp tại đây,
 * không sửa gì trong module Project.
 *
 * Quy tắc (theo plan Dashboard):
 * - Chỉ tính trên task lá (type='task'), loại bỏ status='cancelled' khỏi cả
 *   tử số và mẫu số.
 * - Không có task lá hợp lệ nào → trả null ("Chưa có dữ liệu"), KHÔNG phải 0%.
 * - average: trung bình progress_percent.
 * - duration_weighted: trọng số = số ngày mỗi task (end-start+1, thiếu ngày
 *   thì trọng số = 1).
 * - task_weighted: trọng số = cột weight; nếu mọi task đều không có weight
 *   hợp lệ → fallback average.
 *
 * Cài đặt 1 query tổng hợp cho toàn bộ $projectIds cùng lúc (không N+1).
 */
class ProjectProgressCalculator
{
    /**
     * @param  list<int>  $projectIds
     * @return Collection<int, object> keyBy project_id — mỗi phần tử có avg_progress,
     *                                 total_duration, weighted_duration_sum, total_weight,
     *                                 weighted_task_sum, task_count
     */
    public function forProjects(array $projectIds): Collection
    {
        if (empty($projectIds)) {
            return collect();
        }

        $rows = DB::table('tasks')
            ->select('project_id',
                DB::raw('AVG(progress_percent) as avg_progress'),
                DB::raw('SUM(DATEDIFF(COALESCE(end_date, start_date), COALESCE(start_date, end_date)) + 1) as total_duration'),
                DB::raw('SUM((DATEDIFF(COALESCE(end_date, start_date), COALESCE(start_date, end_date)) + 1) * COALESCE(progress_percent, 0)) as weighted_duration_sum'),
                DB::raw('SUM(COALESCE(weight, 0)) as total_weight'),
                DB::raw('SUM(COALESCE(weight, 0) * COALESCE(progress_percent, 0)) as weighted_task_sum'),
                DB::raw('COUNT(*) as task_count'))
            ->whereIn('project_id', $projectIds)
            ->where('type', 'task')
            ->where('status', '!=', 'cancelled')
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        return $rows;
    }

    /**
     * Tính tiến độ cho 1 project cụ thể dựa trên hàng tổng hợp (từ
     * forProjects()) + progress_method của chính project đó.
     *
     * @param  object|null  $row  1 dòng trong kết quả forProjects(), hoặc null nếu chưa có task
     */
    public function resolve(?object $row, string $progressMethod): ?float
    {
        if ($row === null || (int) $row->task_count === 0) {
            return null;
        }

        return match ($progressMethod) {
            'duration_weighted' => $this->durationWeighted($row),
            'task_weighted' => $this->taskWeighted($row),
            default => $this->average($row),
        };
    }

    /**
     * Tính hàng loạt cho nhiều project cùng lúc — trả map project_id => float|null.
     *
     * @param  Collection  $projects  mỗi phần tử cần có ->id và ->progress_method
     * @return array<int, float|null>
     */
    public function resolveMany(Collection $projects): array
    {
        $projectIds = $projects->pluck('id')->all();
        $rows = $this->forProjects($projectIds);

        $result = [];
        foreach ($projects as $project) {
            $row = $rows[$project->id] ?? null;
            $result[$project->id] = $this->resolve($row, $project->progress_method ?? 'average');
        }

        return $result;
    }

    private function average(object $row): ?float
    {
        return $row->avg_progress !== null ? round((float) $row->avg_progress, 1) : null;
    }

    private function durationWeighted(object $row): ?float
    {
        $totalDuration = (float) $row->total_duration;
        if ($totalDuration <= 0) {
            return $this->average($row);
        }

        return round(((float) $row->weighted_duration_sum) / ($totalDuration * 100) * 100, 1);
    }

    private function taskWeighted(object $row): ?float
    {
        $totalWeight = (float) $row->total_weight;
        if ($totalWeight <= 0) {
            // Không có task nào có weight hợp lệ — fallback về average.
            return $this->average($row);
        }

        return round(((float) $row->weighted_task_sum) / $totalWeight, 1);
    }
}
