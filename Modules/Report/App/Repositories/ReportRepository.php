<?php

namespace Modules\Report\App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Report\App\Models\Report;
use Modules\Report\App\Repositories\Contracts\ReportRepositoryInterface;

class ReportRepository implements ReportRepositoryInterface
{
    public function find(int $id): ?Report
    {
        return Report::query()
            ->with(Report::WITH_PRESENT)
            ->find($id);
    }

    public function allByDepartment(int $departmentId, array $filters = []): Collection
    {
        return $this->baseQuery($filters)
            ->where('department_id', $departmentId)
            ->get();
    }

    public function allSharedWithUser(int $userId, array $filters = []): Collection
    {
        return $this->baseQuery($filters)
            ->whereHas('viewers', fn (Builder $q) => $q->where('user_id', $userId))
            ->get();
    }

    public function allAcrossDepartments(array $filters = []): Collection
    {
        return $this->baseQuery($filters)->get();
    }

    public function create(array $data): Report
    {
        $report = Report::query()->create($data);

        return $report->fresh(Report::WITH_PRESENT);
    }

    public function update(Report $report, array $data): Report
    {
        $report->update($data);

        return $report->fresh(Report::WITH_PRESENT);
    }

    public function delete(Report $report): void
    {
        $report->delete();
    }

    public function syncViewers(Report $report, array $userIds): void
    {
        $report->viewers()->delete();

        foreach (array_values(array_unique($userIds)) as $userId) {
            $report->viewers()->create(['user_id' => $userId]);
        }
    }

    public function syncUserFilters(Report $report, array $userIds): void
    {
        $report->filters()->where('filter_key', 'user_id')->delete();

        foreach (array_values(array_unique($userIds)) as $userId) {
            $report->filters()->create([
                'filter_key' => 'user_id',
                'filter_value' => (string) $userId,
            ]);
        }
    }

    public function syncColumns(Report $report, array $columnKeys): void
    {
        $report->columns()->delete();

        foreach (array_values(array_unique($columnKeys)) as $index => $key) {
            $report->columns()->create([
                'column_key' => $key,
                'sort_order' => $index,
            ]);
        }
    }

    public function syncCriteria(Report $report, array $criterionIds): void
    {
        $report->criteria()->delete();

        foreach (array_values(array_unique($criterionIds)) as $criterionId) {
            $report->criteria()->create(['criterion_id' => $criterionId]);
        }
    }

    public function syncPeopleSnapshot(Report $report, array $people): void
    {
        $report->peopleSnapshot()->delete();

        foreach (array_values($people) as $index => $person) {
            $report->peopleSnapshot()->create([
                'user_id' => (int) $person['id'],
                'user_name' => mb_substr((string) $person['name'], 0, 255),
                'sort_order' => $index,
            ]);
        }
    }

    public function paginateVisible(
        string $scope,
        int $scopeId,
        array $filters,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        $query = $this->baseQuery($filters);

        if ($scope === 'department') {
            $query->where('department_id', $scopeId);
        } elseif ($scope === 'shared') {
            $query->whereHas('viewers', fn (Builder $q) => $q->where('user_id', $scopeId));
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /** @param  array<string, mixed>  $filters */
    private function baseQuery(array $filters): Builder
    {
        $query = Report::query()
            ->with(Report::WITH_PRESENT)
            ->orderByDesc('created_at');

        if (! empty($filters['report_type'])) {
            $query->where('report_type', $filters['report_type']);
        }

        if (! empty($filters['q'])) {
            $query->where('title', 'like', '%'.$filters['q'].'%');
        }

        return $query;
    }
}
