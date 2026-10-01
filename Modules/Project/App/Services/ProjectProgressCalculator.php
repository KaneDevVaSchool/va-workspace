<?php

namespace Modules\Project\App\Services;

use Illuminate\Support\Collection;
use Modules\Project\App\Repositories\Contracts\TaskRepositoryInterface;

/**
 * Tính % tiến độ roll-up của Project từ Task lá — bảng `projects` KHÔNG có cột
 * tiến độ riêng, nên mọi nơi cần "tiến độ dự án" phải đi qua đây để ra cùng
 * một con số.
 *
 * Trước đây lớp này nằm trong module Dashboard và chỉ Dashboard dùng, nên
 * trang Dự án (ProjectService::present()) trả progress_percent = null và hiện
 * "—" trong khi Dashboard hiện đúng %. Chuyển về module Project (chủ sở hữu
 * nghiệp vụ) để cả 2 nơi dùng chung một công thức.
 *
 * Quy tắc:
 * - Chỉ tính trên task lá (type='task'), loại status='cancelled' khỏi cả tử số
 *   và mẫu số.
 * - Không có task lá hợp lệ nào → trả null ("Chưa có dữ liệu"), KHÔNG phải 0%.
 * - average: trung bình progress_percent.
 * - duration_weighted: trọng số = số ngày mỗi task (end-start+1, thiếu ngày thì
 *   trọng số = 1).
 * - task_weighted: trọng số = cột weight; nếu mọi task đều không có weight hợp
 *   lệ → fallback average.
 *
 * Số liệu thô lấy qua TaskRepository (1 query cho cả danh sách project, không
 * N+1) — lớp này không gọi Eloquent/DB trực tiếp, theo §5 CLAUDE.md.
 */
class ProjectProgressCalculator
{
    public function __construct(
        private readonly TaskRepositoryInterface $tasks,
    ) {}

    /**
     * Số liệu tổng hợp theo project — truyền lại vào resolve() để tính.
     *
     * @param  list<int>  $projectIds
     * @return Collection<int, object> keyBy project_id
     */
    public function forProjects(array $projectIds): Collection
    {
        return $this->tasks->progressAggregatesByProject($projectIds);
    }

    /**
     * Tính tiến độ cho 1 project dựa trên hàng tổng hợp (từ forProjects()) +
     * progress_method của chính project đó.
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
     * Tính hàng loạt cho nhiều project cùng lúc — dùng cho danh sách dự án để
     * tránh N+1. Trả map project_id => float|null.
     *
     * @param  Collection  $projects  mỗi phần tử cần có ->id và ->progress_method
     * @return array<int, float|null>
     */
    public function resolveMany(Collection $projects): array
    {
        $rows = $this->forProjects($projects->pluck('id')->all());

        $result = [];
        foreach ($projects as $project) {
            $row = $rows[$project->id] ?? null;
            $result[$project->id] = $this->resolve($row, $project->progress_method ?? 'average');
        }

        return $result;
    }

    /** Tiến độ của đúng 1 project — tiện cho trang chi tiết (1 query). */
    public function resolveOne(int $projectId, ?string $progressMethod): ?float
    {
        $rows = $this->forProjects([$projectId]);

        return $this->resolve($rows[$projectId] ?? null, $progressMethod ?? 'average');
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
