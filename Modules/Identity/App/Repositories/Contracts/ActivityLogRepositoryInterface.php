<?php

namespace Modules\Identity\App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Identity\App\Models\ActivityLog;

interface ActivityLogRepositoryInterface
{
    public function create(array $data): ActivityLog;

    /** @return Collection<int, ActivityLog> */
    public function recent(int $limit = 20): Collection;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ActivityLog>
     */
    public function forExport(array $filters, int $limit): Collection;

    /** @param  array<string, mixed>  $filters */
    public function countFiltered(array $filters): int;

    /** @return Collection<int, ActivityLog> */
    public function distinctActors(): Collection;

    /** @return Collection<int, string> */
    public function distinctSubjectTypes(): Collection;
}
