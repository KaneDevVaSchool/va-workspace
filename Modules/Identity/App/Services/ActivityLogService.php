<?php

namespace Modules\Identity\App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Identity\App\Models\ActivityLog;
use Modules\Identity\App\Repositories\Contracts\ActivityLogRepositoryInterface;
use Throwable;

/**
 * Ghi và đọc nhật ký thao tác. Ghi thất bại không được làm hỏng thao tác gốc.
 */
class ActivityLogService
{
    public function __construct(
        private readonly ActivityLogRepositoryInterface $logs,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        string $action,
        string $description,
        ?User $actor = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $properties = [],
    ): void {
        try {
            $request = request();
            $actor ??= $request?->user();

            $this->logs->create([
                'actor_id' => $actor?->id,
                'actor_name' => $actor?->name,
                'actor_email' => $actor?->email,
                'action' => $action,
                'description' => mb_substr($description, 0, 255),
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'properties' => $properties === [] ? null : $properties,
                'ip_address' => $request?->ip(),
                'user_agent' => $request
                    ? mb_substr((string) $request->userAgent(), 0, 512)
                    : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Không ghi được nhật ký hoạt động.', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return Collection<int, ActivityLog> */
    public function recent(int $limit = 20): Collection
    {
        return $this->logs->recent($limit);
    }

    /**
     * @param  array{q?: string, action?: string}  $filters
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->logs->paginate($filters, $perPage);
    }

    /** @return array<string, mixed> */
    public function present(ActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'description' => $log->description,
            'actor_name' => $log->actor_name,
            'actor_email' => $log->actor_email,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }
}
