<?php

namespace Modules\Identity\App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Identity\App\Models\ActivityLog;
use Modules\Identity\App\Repositories\Contracts\ActivityLogRepositoryInterface;

class ActivityLogRepository implements ActivityLogRepositoryInterface
{
    public function create(array $data): ActivityLog
    {
        return ActivityLog::query()->create($data);
    }

    public function recent(int $limit = 20): Collection
    {
        return ActivityLog::query()
            ->with(['actor.department'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->with(['actor.department'])
            ->paginate($perPage);
    }

    public function forExport(array $filters, int $limit): Collection
    {
        return $this->filteredQuery($filters)
            ->limit($limit)
            ->get();
    }

    public function countFiltered(array $filters): int
    {
        return $this->filteredQuery($filters)->count();
    }

    public function distinctActors(): Collection
    {
        return ActivityLog::query()
            ->select('actor_id', 'actor_name', 'actor_email')
            ->whereNotNull('actor_id')
            ->groupBy('actor_id', 'actor_name', 'actor_email')
            ->orderBy('actor_name')
            ->get();
    }

    public function distinctSubjectTypes(): Collection
    {
        return ActivityLog::query()
            ->whereNotNull('subject_type')
            ->where('subject_type', '!=', '')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type');
    }

    public function recentForSubject(
        string $subjectType,
        array $subjectIds = [],
        array $propertyMatches = [],
        int $limit = 80,
    ): Collection {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $subjectIds),
            fn (int $id) => $id > 0,
        )));

        if ($ids === [] && $propertyMatches === []) {
            return collect();
        }

        $query = ActivityLog::query()
            ->with(['actor.department'])
            ->where('subject_type', $subjectType)
            ->where(function (Builder $inner) use ($ids, $propertyMatches) {
                $started = false;
                if ($ids !== []) {
                    $inner->whereIn('subject_id', $ids);
                    $started = true;
                }
                foreach ($propertyMatches as $key => $value) {
                    $column = 'properties->'.$key;
                    if ($started) {
                        $inner->orWhere($column, $value);
                    } else {
                        $inner->where($column, $value);
                        $started = true;
                    }
                }
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit);

        return $query->get();
    }

    /** @param  array<string, mixed>  $filters */
    private function filteredQuery(array $filters): Builder
    {
        $query = ActivityLog::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function (Builder $inner) use ($like) {
                $inner->where('description', 'like', $like)
                    ->orWhere('actor_name', 'like', $like)
                    ->orWhere('actor_email', 'like', $like)
                    ->orWhere('ip_address', 'like', $like)
                    ->orWhere('action', 'like', $like);
            });
        }

        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '') {
            $query->where('action', $action);
        }

        $actorId = trim((string) ($filters['actor_id'] ?? ''));
        if ($actorId === 'system') {
            $query->whereNull('actor_id');
        } elseif ($actorId !== '') {
            $query->where('actor_id', (int) $actorId);
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        $ip = trim((string) ($filters['ip'] ?? ''));
        if ($ip !== '') {
            $query->where('ip_address', 'like', '%'.$ip.'%');
        }

        $subjectType = trim((string) ($filters['subject_type'] ?? ''));
        if ($subjectType !== '') {
            $query->where('subject_type', $subjectType);
        }

        return $query;
    }
}
