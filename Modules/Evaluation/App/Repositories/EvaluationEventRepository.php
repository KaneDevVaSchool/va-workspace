<?php

namespace Modules\Evaluation\App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Evaluation\App\Models\EvaluationEvent;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationEventRepositoryInterface;

class EvaluationEventRepository implements EvaluationEventRepositoryInterface
{
    public function find(int $id): ?EvaluationEvent
    {
        return EvaluationEvent::query()
            ->with(EvaluationEvent::WITH_PRESENT)
            ->find($id);
    }

    public function allByDepartment(int $departmentId, array $filters = []): Collection
    {
        return $this->filteredQuery($departmentId, $filters)->get();
    }

    public function paginateByDepartment(
        int $departmentId,
        array $filters,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        return $this->filteredQuery($departmentId, $filters)
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /** @param  array<string, mixed>  $filters */
    private function filteredQuery(int $departmentId, array $filters): Builder
    {
        $query = EvaluationEvent::query()
            ->with(EvaluationEvent::WITH_PRESENT)
            ->where('department_id', $departmentId);

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('occurred_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('occurred_at', '<=', $filters['to']);
        }

        if (! empty($filters['q'])) {
            $needle = '%'.$filters['q'].'%';
            $query->where(function (Builder $sub) use ($needle) {
                $sub->where('reason', 'like', $needle)
                    ->orWhere('level_label', 'like', $needle)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $needle));
            });
        }

        return $query
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');
    }

    public function approvedForUserInPeriod(
        int $departmentId,
        int $userId,
        string $from,
        string $to,
    ): Collection {
        return EvaluationEvent::query()
            ->with(['criterion'])
            ->where('department_id', $departmentId)
            ->where('user_id', $userId)
            ->where('status', EvaluationEvent::STATUS_APPROVED)
            ->whereDate('occurred_at', '>=', $from)
            ->whereDate('occurred_at', '<=', $to)
            ->orderBy('occurred_at')
            ->get();
    }

    public function approvedForDepartmentInPeriod(
        int $departmentId,
        array $userIds,
        string $from,
        string $to,
    ): Collection {
        $query = EvaluationEvent::query()
            ->where('department_id', $departmentId)
            ->where('status', EvaluationEvent::STATUS_APPROVED)
            ->whereDate('occurred_at', '>=', $from)
            ->whereDate('occurred_at', '<=', $to);

        if ($userIds !== []) {
            $query->whereIn('user_id', $userIds);
        }

        return $query
            ->orderBy('occurred_at')
            ->get()
            ->groupBy('user_id');
    }

    public function existsSimilar(
        int $departmentId,
        int $userId,
        int $criterionId,
        string $levelCode,
        string $occurredAt,
        ?int $taskId,
    ): bool {
        $query = EvaluationEvent::query()
            ->where('department_id', $departmentId)
            ->where('user_id', $userId)
            ->where('criterion_id', $criterionId)
            ->where('level_code', $levelCode)
            ->whereDate('occurred_at', $occurredAt)
            ->where('status', '!=', EvaluationEvent::STATUS_REJECTED);

        // Ghi nhận gắn công việc và ghi nhận rời là hai chuyện khác nhau, nên
        // chỉ coi là trùng khi cùng thuộc về một công việc (hoặc cùng không).
        if ($taskId === null) {
            $query->whereNull('task_id');
        } else {
            $query->where('task_id', $taskId);
        }

        return $query->exists();
    }

    public function create(array $data): EvaluationEvent
    {
        $event = EvaluationEvent::query()->create($data);

        return $event->fresh(EvaluationEvent::WITH_PRESENT);
    }

    public function update(EvaluationEvent $event, array $data): EvaluationEvent
    {
        $event->update($data);

        return $event->fresh(EvaluationEvent::WITH_PRESENT);
    }

    public function delete(EvaluationEvent $event): void
    {
        $event->delete();
    }
}
