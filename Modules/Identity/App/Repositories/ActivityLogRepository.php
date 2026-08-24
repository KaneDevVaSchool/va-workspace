<?php

namespace Modules\Identity\App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = ActivityLog::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('description', 'like', $like)
                    ->orWhere('actor_name', 'like', $like)
                    ->orWhere('actor_email', 'like', $like);
            });
        }

        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '') {
            $query->where('action', $action);
        }

        return $query->paginate($perPage);
    }
}
